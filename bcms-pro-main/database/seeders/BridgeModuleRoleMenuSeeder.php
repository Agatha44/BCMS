<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BridgeModuleRoleMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('bridge_module_role_menu')
            ->upsert(
                [
                    [
                        'id' => 1,
                        'role_id' => 1,
                        'menu_id' => 4,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 2,
                        'role_id' => 1,
                        'menu_id' => 1,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 3,
                        'role_id' => 7,
                        'menu_id' => 4,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 4,
                        'role_id' => 7,
                        'menu_id' => 1,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 5,
                        'role_id' => 7,
                        'menu_id' => 3,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 6,
                        'role_id' => 7,
                        'menu_id' => 5,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 7,
                        'role_id' => 6,
                        'menu_id' => 4,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 8,
                        'role_id' => 6,
                        'menu_id' => 1,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 9,
                        'role_id' => 6,
                        'menu_id' => 2,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 10,
                        'role_id' => 15,
                        'menu_id' => 13,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 11,
                        'role_id' => 16,
                        'menu_id' => 14,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 12,
                        'role_id' => 16,
                        'menu_id' => 13,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 13,
                        'role_id' => 17,
                        'menu_id' => 13,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 14,
                        'role_id' => 17,
                        'menu_id' => 14,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 15,
                        'role_id' => 18,
                        'menu_id' => 13,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 16,
                        'role_id' => 17,
                        'menu_id' => 20,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 17,
                        'role_id' => 1,
                        'menu_id' => 17,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 18,
                        'role_id' => 6,
                        'menu_id' => 17,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 19,
                        'role_id' => 7,
                        'menu_id' => 17,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                ],
                ['id'],
                [
                    'role_id',
                    'menu_id',
                    'is_active',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Bridge module role menus seeded successfully into bcmis2.bridge_module_role_menu.');
    }
}
