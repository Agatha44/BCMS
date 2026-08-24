<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BridgeModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('bridge_module')
            ->upsert(
                [
                    [
                        'id' => 2,
                        'icon' => 'UsersIcon',
                        'title' => 'Employee Management',
                        'description' => 'Manages bridge employees, Register new Employee and Bridge administration.',
                        'module_id' => 'employee-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 3,
                        'icon' => 'BuildingOfficeIcon',
                        'title' => 'Toll Management',
                        'description' => 'Manages Bridge Collections, configuration and administration of collection points.',
                        'module_id' => 'collection-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 4,
                        'icon' => 'CalendarIcon',
                        'title' => 'Leave Management',
                        'description' => 'Employees leave request and Manages Leave Approval.',
                        'module_id' => 'leave-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 5,
                        'icon' => 'CurrencyDollarIcon',
                        'title' => 'Payroll Management',
                        'description' => 'Salary information and processing.',
                        'module_id' => 'payroll-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 6,
                        'icon' => 'ReceiptPercentIcon',
                        'title' => 'Allowance Management',
                        'description' => 'Manages employees allowances.',
                        'module_id' => 'allowance-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 7,
                        'icon' => 'ExclamationTriangleIcon',
                        'title' => 'Incident Management',
                        'description' => 'Track, manage and resolve incidents and issues efficiently.',
                        'module_id' => 'incident-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 8,
                        'icon' => 'KeyIcon',
                        'title' => 'Account Management',
                        'description' => 'Manage user access, permissions and security controls.',
                        'module_id' => 'account-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 9,
                        'icon' => 'Bell',
                        'title' => 'Notification Management',
                        'description' => 'This module manages system generated notifications',
                        'module_id' => 'notification-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 10,
                        'icon' => 'BuildingOfficeIcon',
                        'title' => 'Administration Management',
                        'description' => 'System configuration, report engine, and other administration settings.',
                        'module_id' => 'administration-management',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-08 00:00:00',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                ],
                ['id'],
                [
                    'icon',
                    'title',
                    'description',
                    'module_id',
                    'is_active',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Bridge modules seeded successfully into bcmis2.bridge_module.');
    }
}
