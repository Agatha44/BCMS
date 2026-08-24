<?php

namespace Database\Seeders\ReportEngine;

use App\Models\ReportEngineDefinition;

/**
 * Create report-engine definitions on first install; backfill SQL when missing.
 */
trait SeedsReportEngineDefinitions
{
    /**
     * @param  array<string, mixed>  $definition
     */
    protected function seedReportDefinition(int $moduleRegistrationId, array $definition): ReportEngineDefinition
    {
        $key = $definition['key'] ?? str_replace('-', '_', $definition['script']);

        $existing = ReportEngineDefinition::query()
            ->where('module_registration_id', $moduleRegistrationId)
            ->where('script', $definition['script'])
            ->first();

        if ($existing) {
            $updates = [
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'category_key' => $definition['category_key'] ?? $existing->category_key,
                'handler' => $definition['handler'] ?? $definition['script'],
                'params' => $definition['params'] ?? $existing->params,
                'output_columns' => $definition['output_columns'] ?? $existing->output_columns,
                'options' => $definition['options'] ?? $existing->options,
                'sort_order' => $definition['sort_order'] ?? $existing->sort_order,
                'is_active' => $definition['is_active'] ?? $existing->is_active,
            ];

            if (empty($existing->query) && !empty($definition['query'])) {
                $updates['query'] = $definition['query'];
            }

            $existing->update($updates);

            return $existing->fresh();
        }

        return ReportEngineDefinition::create([
            'module_registration_id' => $moduleRegistrationId,
            'category_key' => $definition['category_key'],
            'key' => $key,
            'name' => $definition['name'],
            'description' => $definition['description'],
            'script' => $definition['script'],
            'handler' => $definition['handler'] ?? $definition['script'],
            'query' => $definition['query'] ?? null,
            'params' => $definition['params'] ?? null,
            'output_columns' => $definition['output_columns'] ?? null,
            'options' => $definition['options'] ?? null,
            'sort_order' => $definition['sort_order'] ?? 0,
            'is_active' => $definition['is_active'] ?? true,
        ]);
    }
}
