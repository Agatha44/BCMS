<?php

use Database\Seeders\ReportEngine\CollectionReportQueries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('report_engine_definitions', 'query')) {
            return;
        }

        foreach (CollectionReportQueries::all() as $script => $query) {
            DB::table('report_engine_definitions')
                ->whereNull('query')
                ->where(function ($builder) use ($script) {
                    $builder->where('script', $script)->orWhere('handler', $script);
                })
                ->update(['query' => $query]);
        }
    }

    public function down(): void
    {
        // Non-destructive: leave backfilled queries in place.
    }
};
