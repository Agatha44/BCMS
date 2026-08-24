<?php

namespace Database\Seeders;

use App\Models\ReportEngineDefinition;
use App\Models\ReportEngineModuleRegistration;
use Database\Seeders\ReportEngine\CollectionReportDefinitions;
use Database\Seeders\ReportEngine\SeedsReportEngineDefinitions;
use Illuminate\Database\Seeder;

class ReportEngineCollectionManagementSeeder extends Seeder
{
    use SeedsReportEngineDefinitions;

    public function run(): void
    {
        $registration = ReportEngineModuleRegistration::updateOrCreate(
            ['route_slug' => 'collection-management'],
            [
                'bridge_module_id' => null,
                'menu_label' => 'Reports',
                'module_path_prefix' => '/collection-management/collection-reports',
                'description' => 'Collection, shift, payment, and audit reports for toll operations',
                'is_active' => true,
            ]
        );

        foreach (CollectionReportDefinitions::all() as $definition) {
            $this->seedReportDefinition($registration->id, $definition);
        }

        $this->command?->info('Report engine: collection-management module seeded with ' . count(CollectionReportDefinitions::all()) . ' reports.');
    }
}
