<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('overtime_batches', function (Blueprint $table) {
            $table->string('payment_status', 50)
                ->nullable()
                ->after('payment_reference')
                ->comment('Latest paymentStatus from ERMS payable callbacks (e.g. CANCELLED).');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('overtime_batches', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};

