<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('bcmis2')->table('employee_deduction', function (Blueprint $table) {
            $table->decimal('original_amount', 15, 2)
                ->nullable()
                ->comment('Original borrowed amount')
                ->after('total_deduction_amount');

            $table->decimal('remaining_amount', 15, 2)
                ->nullable()
                ->comment('Outstanding balance')
                ->after('original_amount');

            $table->unsignedInteger('payment_period')
                ->nullable()
                ->comment('Total repayment months')
                ->after('remaining_amount');

            $table->unsignedInteger('remaining_period')
                ->nullable()
                ->comment('Remaining repayment months')
                ->after('payment_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('bcmis2')->table('employee_deduction', function (Blueprint $table) {
            $table->dropColumn([
                'original_amount',
                'remaining_amount',
                'payment_period',
                'remaining_period',
            ]);
        });
    }
};
