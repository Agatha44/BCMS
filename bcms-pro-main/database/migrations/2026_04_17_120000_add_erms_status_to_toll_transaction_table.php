<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toll_transaction', function (Blueprint $table) {
            // 0 = not sent, 1 = sent/posted, 2 = failed
            $table->unsignedTinyInteger('erms_status')
                ->default(0)
                ->after('charged_amount')
                ->comment('ERMS submission status: 0 not sent, 1 sent, 2 failed');

            $table->index(['erms_status'], 'idx_toll_transaction_erms_status');
        });
    }

    public function down(): void
    {
        Schema::table('toll_transaction', function (Blueprint $table) {
            $table->dropIndex('idx_toll_transaction_erms_status');
            $table->dropColumn('erms_status');
        });
    }
};

