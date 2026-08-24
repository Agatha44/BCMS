<?php

namespace App\Services\Administration;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuthRoleModuleService
{
    /**
     * @param  array<int>  $authRoleIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function getModulesGroupedByAuthRoleId(array $authRoleIds): array
    {
        if ($authRoleIds === []) {
            return [];
        }

        $rows = DB::connection('bcmis2')
            ->table('auth_role_module as arm')
            ->join('bridge_module as bm', 'arm.module_id', '=', 'bm.id')
            ->whereIn('arm.auth_role_id', $authRoleIds)
            ->where('arm.is_active', true)
            ->where('bm.is_active', true)
            ->select(
                'arm.id as auth_role_module_id',
                'arm.auth_role_id',
                'bm.id',
                'bm.title',
                'bm.icon',
                'bm.module_id',
                'bm.description',
                'bm.is_active'
            )
            ->orderBy('bm.title')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->auth_role_id][] = $this->formatModule($row);
        }

        return $grouped;
    }

    /**
     * @param  array<int>  $authRoleIds
     * @return Collection<int, object>
     */
    public function getAccessibleModulesForAuthRoleIds(array $authRoleIds): Collection
    {
        if ($authRoleIds === []) {
            return collect();
        }

        return DB::connection('bcmis2')
            ->table('auth_role_module as arm')
            ->join('bridge_module as bm', 'arm.module_id', '=', 'bm.id')
            ->whereIn('arm.auth_role_id', $authRoleIds)
            ->where('arm.is_active', true)
            ->where('bm.is_active', true)
            ->select(
                'bm.id',
                'bm.title as module_name',
                'bm.icon as module_icon',
                'bm.module_id as module_identifier',
                'bm.description as module_description',
                'bm.is_active as module_is_active',
                'arm.auth_role_id'
            )
            ->distinct()
            ->orderBy('bm.title')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatModule(object $row): array
    {
        return [
            'auth_role_module_id' => (int) $row->auth_role_module_id,
            'id' => (int) $row->id,
            'module_name' => $row->title,
            'module_icon' => $row->icon,
            'module_identifier' => $row->module_id,
            'module_description' => $row->description,
            'module_is_active' => (bool) $row->is_active,
        ];
    }

    public function assignModuleToAuthRole(int $authRoleId, int $moduleId, ?int $actorId = null): array
    {
        $module = DB::connection('bcmis2')->table('bridge_module')->where('id', $moduleId)->first();
        if (!$module) {
            throw new \InvalidArgumentException('Bridge module not found');
        }

        $existing = DB::connection('bcmis2')
            ->table('auth_role_module')
            ->where('auth_role_id', $authRoleId)
            ->where('module_id', $moduleId)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                return ['message' => 'Module already assigned to role', 'created' => false];
            }

            DB::connection('bcmis2')
                ->table('auth_role_module')
                ->where('id', $existing->id)
                ->update([
                    'is_active' => true,
                    'modified_by' => $actorId,
                    'updated_at' => now(),
                ]);

            return ['message' => 'Module access reactivated for role', 'created' => false];
        }

        DB::connection('bcmis2')->table('auth_role_module')->insert([
            'auth_role_id' => $authRoleId,
            'module_id' => $moduleId,
            'is_active' => true,
            'created_by' => $actorId,
            'modified_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['message' => 'Module assigned to role successfully', 'created' => true];
    }
}
