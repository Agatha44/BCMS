<?php

namespace App\Services\Reports;

use App\Models\ReportEngineDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Executes report-engine definitions that store a read-only SQL query.
 */
class ReportEngineSqlExecutor
{
    /**
     * @return array{success: bool, status_code: int, data: mixed, message: string}
     */
    public function execute(ReportEngineDefinition $definition, array $params): array
    {
        $query = rtrim(trim((string) $definition->query), ';');
        if ($query === '') {
            if (!Schema::hasColumn('report_engine_definitions', 'query')) {
                throw new \InvalidArgumentException(
                    "Report '{$definition->name}' cannot run: the query column is missing. "
                    . 'Run: php artisan migrate --path=database/migrations/report_engine'
                );
            }

            throw new \InvalidArgumentException(
                "Report '{$definition->name}' ({$definition->script}) has no SQL query saved. "
                . 'Edit it in Administration Management → Report Engine and paste the SQL query.'
            );
        }

        $this->assertSelectOnly($query);

        $definitionParams = is_array($definition->params) ? $definition->params : [];
        $this->validateParams($definitionParams, $params);
        [$query, $bindings] = $this->prepareQueryBindings($query, $params);

        $rows = array_map(
            static fn ($row) => (array) $row,
            DB::select($query, $bindings)
        );

        $paginated = (bool) (($definition->options['paginated'] ?? false));

        return $this->formatResponse($rows, $definition->name, $params, $paginated);
    }

    protected function assertSelectOnly(string $query): void
    {
        $normalized = preg_replace('/\s+/', ' ', trim($query));

        if (!preg_match('/^SELECT\b/i', $normalized)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed for report definitions');
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|REPLACE|GRANT|REVOKE)\b/i', $normalized)) {
            throw new \InvalidArgumentException('Only read-only SELECT queries are allowed for report definitions');
        }
    }

    protected function validateParams(array $definitionParams, array $params): void
    {
        $rules = [];

        foreach ($definitionParams as $name => $config) {
            $rule = [];

            if (($config['required'] ?? false) === true) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }

            $type = $config['type'] ?? 'string';
            if ($type === 'date') {
                $rule[] = 'date_format:Y-m-d';
            } elseif ($type === 'integer') {
                $rule[] = 'integer';
            } elseif ($type === 'decimal') {
                $rule[] = 'numeric';
            }

            $rules[$name] = $rule;
        }

        if ($rules === []) {
            return;
        }

        $validator = Validator::make($params, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * PDO/MySQL does not allow reusing the same named placeholder more than once.
     * Duplicate :param tokens are rewritten to :param_0, :param_1, etc.
     * Empty optional values are inlined as NULL.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function prepareQueryBindings(string $query, array $params): array
    {
        preg_match_all('/:(\w+)/', $query, $matches);
        $placeholderNames = array_values(array_unique($matches[1] ?? []));

        $normalizedParams = [];
        foreach ($placeholderNames as $name) {
            if (in_array($name, ['page', 'per_page', 'search'], true)) {
                continue;
            }

            $value = $params[$name] ?? null;
            $normalizedParams[$name] = ($value === '' || $value === null) ? null : $value;
        }

        $uniqueParams = [];
        $modifiedQuery = $query;

        foreach ($normalizedParams as $key => $value) {
            if ($value === null) {
                $modifiedQuery = preg_replace("/:{$key}\b/", 'NULL', $modifiedQuery);
                continue;
            }

            $count = 0;
            $modifiedQuery = preg_replace_callback(
                "/:{$key}\b/",
                function () use (&$count, $key, $value, &$uniqueParams) {
                    $uniqueName = "{$key}_{$count}";
                    $uniqueParams[$uniqueName] = $value;
                    $count++;

                    return ":{$uniqueName}";
                },
                $modifiedQuery
            );
        }

        return [$modifiedQuery, $uniqueParams];
    }

    /**
     * @return array{success: bool, status_code: int, data: mixed, message: string}
     */
    protected function formatResponse(array $rows, string $reportName, array $params, bool $paginated): array
    {
        $page = max((int) ($params['page'] ?? 1), 1);
        $perPage = min(max((int) ($params['per_page'] ?? 15), 1), 200);
        $total = count($rows);

        if ($paginated) {
            $slice = array_values(array_slice($rows, ($page - 1) * $perPage, $perPage));
            $data = [
                'rows' => $slice,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) max(1, (int) ceil($total / max($perPage, 1))),
                ],
                'from_date' => $params['from_date'] ?? null,
                'to_date' => $params['to_date'] ?? null,
                'total' => $total,
            ];
        } else {
            $data = $rows;
        }

        return [
            'success' => true,
            'status_code' => 1,
            'data' => $data,
            'message' => $total === 0 ? 'No records found' : "{$reportName} generated",
        ];
    }
}
