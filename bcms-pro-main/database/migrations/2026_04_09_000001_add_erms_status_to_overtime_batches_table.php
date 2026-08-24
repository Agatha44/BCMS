<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('overtime_batches', function (Blueprint $table) {
            // 0 = not sent, 1 = sent, 2 = failed
            $table->unsignedTinyInteger('erms_status')
                ->default(0)
                ->after('external_response')
                ->comment('ERMS submission status: 0 not sent, 1 sent, 2 failed');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('overtime_batches', function (Blueprint $table) {
            $table->dropColumn('erms_status');
        });
    }
};

