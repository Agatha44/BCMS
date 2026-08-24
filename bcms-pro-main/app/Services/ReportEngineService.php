<?php

namespace App\Services;

use App\Models\ReportEngineDefinition;
use App\Models\ReportEngineModuleRegistration;
use App\Services\Audit\OwenItAuditWriter;
use App\Services\Reports\CollectionReportExecutor;
use App\Services\Reports\ReportEngineSqlExecutor;
use App\Support\Reports\ReportParamSchema;
use Database\Seeders\ReportEngine\CollectionReportDefinitions;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportEngineService
{
    public function __construct(
        protected CollectionReportExecutor $executor,
        protected ReportEngineSqlExecutor $sqlExecutor,
        protected OwenItAuditWriter $auditWriter
    ) {
    }

    public function resolveModuleRegistration(string $moduleSlug): ReportEngineModuleRegistration
    {
        $registration = ReportEngineModuleRegistration::where('route_slug', $moduleSlug)
            ->where('is_active', true)
            ->first();

        if (!$registration) {
            throw new \InvalidArgumentException("Report engine module '{$moduleSlug}' is not registered or inactive");
        }

        return $registration;
    }

    public function getActiveRegistrations(): array
    {
        return ReportEngineModuleRegistration::where('is_active', true)
            ->orderBy('menu_label')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'menu_label' => $r->menu_label,
                'route_slug' => $r->route_slug,
                'module_path_prefix' => $r->module_path_prefix,
                'description' => $r->description,
            ])
            ->values()
            ->all();
    }

    public function getRegistrationsForAdmin(): array
    {
        return ReportEngineModuleRegistration::withCount([
            'definitions as reports_count' => fn ($q) => $q->where('is_active', true),
        ])
            ->orderBy('route_slug')
            ->get()
            ->toArray();
    }

    public function getCatalog(string $moduleSlug): array
    {
        $registration = $this->resolveModuleRegistration($moduleSlug);
        $definitions = ReportEngineDefinition::where('module_registration_id', $registration->id)
            ->where('is_active', true)
            ->orderBy('category_key')
            ->orderBy('sort_order')
            ->get();

        $categories = [];
        foreach (CollectionReportDefinitions::CATEGORIES as $key => $meta) {
            $categoryReports = $definitions->where('category_key', $key)->values();
            if ($categoryReports->isEmpty()) {
                continue;
            }
            $categories[] = [
                'key' => $key,
                'label' => $meta['label'],
                'shortLabel' => $meta['short_label'],
                'description' => $meta['description'],
                'reportCount' => $categoryReports->count(),
                'reports' => $categoryReports->map(fn ($d) => $this->formatDefinition($d))->values()->all(),
            ];
        }

        return [
            'registration' => [
                'id' => $registration->id,
                'menu_label' => $registration->menu_label,
                'route_slug' => $registration->route_slug,
                'module_path_prefix' => $registration->module_path_prefix,
                'description' => $registration->description,
            ],
            'categories' => $categories,
        ];
    }

    public function getAvailableReports(string $moduleSlug): array
    {
        $registration = $this->resolveModuleRegistration($moduleSlug);

        return ReportEngineDefinition::where('module_registration_id', $registration->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($report) => [
                $report->script => $this->formatDefinition($report),
            ])
            ->all();
    }

    public function getReportParams(string $moduleSlug, string $reportIdentifier): array
    {
        $report = $this->findActiveReport($moduleSlug, $reportIdentifier);

        return [
            'report' => $this->formatDefinition($report),
            'query_params' => $report->params ?? [],
            'filter_keys' => ReportParamSchema::filterKeys($report->params ?? []),
        ];
    }

    public function generateReport(string $moduleSlug, string $reportIdentifier, array $params): array
    {
        $report = $this->findActiveReport($moduleSlug, $reportIdentifier);

        if (CollectionReportExecutor::isKnownHandler($report->handler)) {
            $result = $this->executor->execute($report->handler, $params);
        } else {
            $result = $this->sqlExecutor->execute($report, $params);
        }

        if (($result['success'] ?? false) === true) {
            $this->recordReportAudit('generated', $moduleSlug, $report, $params, $result);
        }

        return $result;
    }

    public function logReportExport(string $moduleSlug, string $reportIdentifier, array $params): void
    {
        $report = $this->findActiveReport($moduleSlug, $reportIdentifier);
        $this->recordReportAudit('exported', $moduleSlug, $report, $params);
    }

    public function listDefinitionsForAdmin(int $registrationId): array
    {
        return ReportEngineDefinition::withTrashed()
            ->where('module_registration_id', $registrationId)
            ->orderBy('category_key')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($d) => $this->formatDefinitionForAdmin($d))
            ->values()
            ->all();
    }

    public function formatDefinitionForAdmin(ReportEngineDefinition $report): array
    {
        return $this->formatDefinition($report, true);
    }

    public function createDefinition(int $registrationId, array $data): ReportEngineDefinition
    {
        $data['module_registration_id'] = $registrationId;
        $data['key'] = $data['key'] ?? ($data['script'] ?? Str::snake($data['name']));
        $data['script'] = $data['script'] ?? Str::slug($data['name']);
        $data['handler'] = $data['handler'] ?? $data['script'];

        return ReportEngineDefinition::create($data);
    }

    public function updateDefinition(ReportEngineDefinition $definition, array $data): ReportEngineDefinition
    {
        $definition->update($data);

        return $definition->fresh();
    }

    public function deleteDefinition(ReportEngineDefinition $definition): void
    {
        $definition->delete();
    }

    public function restoreDefinition(int $definitionId): ReportEngineDefinition
    {
        $definition = ReportEngineDefinition::withTrashed()->findOrFail($definitionId);
        $definition->restore();

        return $definition;
    }

    protected function findActiveReport(string $moduleSlug, string $identifier): ReportEngineDefinition
    {
        $registration = $this->resolveModuleRegistration($moduleSlug);

        $report = ReportEngineDefinition::where('module_registration_id', $registration->id)
            ->where('is_active', true)
            ->where(function ($q) use ($identifier) {
                $q->where('script', $identifier)->orWhere('key', $identifier);
            })
            ->first();

        if (!$report) {
            throw new \InvalidArgumentException("Report '{$identifier}' not found in module '{$moduleSlug}'");
        }

        return $report;
    }

    protected function recordReportAudit(
        string $event,
        string $moduleSlug,
        ReportEngineDefinition $report,
        array $params,
        ?array $result = null
    ): void {
        $auditParams = Arr::except($params, ['format', 'row_count', 'page', 'per_page', 'search']);
        $newValues = [
            'module_slug' => $moduleSlug,
            'report_name' => $report->name,
            'report_script' => $report->script,
            'handler' => $report->handler,
            'filters' => $auditParams,
        ];

        if ($event === 'exported') {
            $newValues['format'] = $params['format'] ?? null;
            $newValues['row_count'] = isset($params['row_count']) ? (int) $params['row_count'] : null;
        } else {
            $newValues['row_count'] = $this->extractRowCountFromResult($result ?? []);
        }

        $this->auditWriter->record(
            $event,
            ReportEngineDefinition::class,
            $report->id,
            null,
            $newValues,
            'report-engine'
        );
    }

    protected function extractRowCountFromResult(array $result): ?int
    {
        $data = $result['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['pagination']['total'])) {
            return (int) $data['pagination']['total'];
        }

        if (isset($data['data']) && is_array($data['data']) && isset($data['pagination']['total'])) {
            return (int) $data['pagination']['total'];
        }

        $rows = $data['rows'] ?? $data['records'] ?? (is_array($data['data'] ?? null) ? $data['data'] : null);
        if (is_array($rows)) {
            return count($rows);
        }

        if (array_is_list($data)) {
            return count($data);
        }

        return null;
    }

    protected function formatDefinition(ReportEngineDefinition $report, bool $admin = false): array
    {
        $options = $report->options ?? [];
        $payload = [
            'id' => $report->key,
            'label' => $report->name,
            'name' => $report->name,
            'description' => $report->description,
            'script' => $report->script,
            'handler' => $report->handler,
            'query' => $report->query,
            'category_key' => $report->category_key,
            'apiSlug' => $report->handler,
            'filters' => ReportParamSchema::filterKeys($report->params ?? []),
            'params' => $report->params ?? [],
            'columns' => $report->output_columns ?? [],
            'output_columns' => $report->output_columns ?? [],
            'paginated' => (bool) ($options['paginated'] ?? false),
            'nested' => (bool) ($options['nested'] ?? false),
            'options' => $options,
            'is_active' => $report->is_active,
            'sort_order' => $report->sort_order,
        ];

        if ($admin) {
            $payload['definition_id'] = $report->id;
            $payload['deleted_at'] = $report->deleted_at;
        }

        return $payload;
    }
}
