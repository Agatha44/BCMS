<?php

namespace App\Http\Controllers;

use App\Models\ReportEngineDefinition;
use App\Models\ReportEngineModuleRegistration;
use App\Services\ReportEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Support\Reports\ReportParamSchema;

class ReportEngineController extends BasicController
{
    public function __construct(
        protected ReportEngineService $engine
    ) {
    }

    public function activeRegistrations(): JsonResponse
    {
        return $this->sendResponse($this->engine->getActiveRegistrations(), 'Active report modules');
    }

    public function listRegistrations(): JsonResponse
    {
        return $this->sendResponse($this->engine->getRegistrationsForAdmin(), 'Report engine registrations');
    }

    public function storeRegistration(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'bridge_module_id' => 'nullable|integer',
            'menu_label' => 'required|string|max:100',
            'route_slug' => 'required|string|max:100|unique:report_engine_module_registrations,route_slug',
            'module_path_prefix' => 'required|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $registration = ReportEngineModuleRegistration::create($v->validated());

        return $this->sendResponse($registration, 'Module registration created');
    }

    public function updateRegistration(Request $request, int $id): JsonResponse
    {
        $registration = ReportEngineModuleRegistration::findOrFail($id);
        $v = Validator::make($request->all(), [
            'bridge_module_id' => 'nullable|integer',
            'menu_label' => 'sometimes|string|max:100',
            'route_slug' => 'sometimes|string|max:100|unique:report_engine_module_registrations,route_slug,' . $id,
            'module_path_prefix' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $registration->update($v->validated());

        return $this->sendResponse($registration->fresh(), 'Module registration updated');
    }

    public function catalog(string $moduleSlug): JsonResponse
    {
        try {
            return $this->sendResponse($this->engine->getCatalog($moduleSlug), 'Report catalog');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 404);
        }
    }

    public function listReports(string $moduleSlug): JsonResponse
    {
        try {
            return $this->sendResponse($this->engine->getAvailableReports($moduleSlug), 'Available reports');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 404);
        }
    }

    public function reportParams(string $moduleSlug, string $reportName): JsonResponse
    {
        try {
            return $this->sendResponse($this->engine->getReportParams($moduleSlug, $reportName), 'Report parameters');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 404);
        }
    }

    public function generateReport(Request $request, string $moduleSlug, string $reportName): JsonResponse
    {
        try {
            $result = $this->engine->generateReport($moduleSlug, $reportName, $request->all());

            return response()->json($result);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 0, 422);
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 404);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 0, 500);
        }
    }

    public function logReportExport(Request $request, string $moduleSlug, string $reportName): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'format' => 'required|string|in:pdf,excel',
            'row_count' => 'nullable|integer|min:0',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        try {
            $this->engine->logReportExport($moduleSlug, $reportName, $request->all());

            return $this->sendResponse(null, 'Report export logged');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 0, 404);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage(), [], 0, 500);
        }
    }

    public function adminDefinitions(int $registrationId): JsonResponse
    {
        return $this->sendResponse(
            $this->engine->listDefinitionsForAdmin($registrationId),
            'Report definitions'
        );
    }

    public function showDefinition(int $definitionId): JsonResponse
    {
        $definition = ReportEngineDefinition::withTrashed()->findOrFail($definitionId);

        return $this->sendResponse(
            $this->engine->formatDefinitionForAdmin($definition),
            'Report definition'
        );
    }

    public function storeDefinition(Request $request, int $registrationId): JsonResponse
    {
        ReportEngineModuleRegistration::findOrFail($registrationId);
        $v = Validator::make($request->all(), [
            'category_key' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'key' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'script' => 'nullable|string|max:200',
            'handler' => 'nullable|string|max:200',
            'query' => 'nullable|string',
            'params' => 'nullable|array',
            'output_columns' => 'nullable|array',
            'options' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $validated = $v->validated();
        if (!empty($validated['params']) && is_array($validated['params'])) {
            $validated['params'] = $this->normalizeParamsInput($validated['params']);
        }

        $definition = $this->engine->createDefinition($registrationId, $validated);

        return $this->sendResponse(
            $this->engine->formatDefinitionForAdmin($definition),
            'Report definition created'
        );
    }

    public function updateDefinition(Request $request, int $definitionId): JsonResponse
    {
        $definition = ReportEngineDefinition::findOrFail($definitionId);
        $v = Validator::make($request->all(), [
            'category_key' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'script' => 'sometimes|string|max:200',
            'handler' => 'sometimes|string|max:200',
            'query' => 'nullable|string',
            'params' => 'nullable|array',
            'output_columns' => 'nullable|array',
            'options' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);
        if ($v->fails()) {
            return $this->sendError('Validation failed', $v->errors()->toArray(), 0, 422);
        }

        $validated = $v->validated();
        if (!empty($validated['params']) && is_array($validated['params'])) {
            $validated['params'] = $this->normalizeParamsInput($validated['params']);
        }

        $updated = $this->engine->updateDefinition($definition, $validated);

        return $this->sendResponse(
            $this->engine->formatDefinitionForAdmin($updated),
            'Report definition updated'
        );
    }

    public function deleteDefinition(int $definitionId): JsonResponse
    {
        $definition = ReportEngineDefinition::findOrFail($definitionId);
        $this->engine->deleteDefinition($definition);

        return $this->sendResponse(null, 'Report definition deleted');
    }

    public function restoreDefinition(int $definitionId): JsonResponse
    {
        $definition = $this->engine->restoreDefinition($definitionId);

        return $this->sendResponse($definition, 'Report definition restored');
    }

    /**
     * Preserve full param schemas from the script board; fall back to filter-key mapping.
     */
    private function normalizeParamsInput(array $params): array
    {
        if ($params === []) {
            return [];
        }

        if (array_is_list($params)) {
            return ReportParamSchema::fromFilters($params);
        }

        $first = reset($params);
        if (is_array($first) && array_key_exists('type', $first)) {
            return $params;
        }

        return ReportParamSchema::fromFilters(array_keys($params));
    }
}
