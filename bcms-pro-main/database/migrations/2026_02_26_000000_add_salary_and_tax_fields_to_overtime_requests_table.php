<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalaryAndTaxFieldsToOvertimeRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            $table->decimal('tax', 12, 2)
                ->nullable()
                ->after('total_amount')
                ->comment('Withholding tax amount (30%)');

            $table->decimal('gross_pay', 12, 2)
                ->nullable()
                ->after('tax')
                ->comment('Gross overtime pay before tax (derived from net pay)');

            $table->decimal('gross_required', 12, 2)
                ->nullable()
                ->after('gross_pay')
                ->comment('Maximum allowed gross pay (half of salary)');

            $table->decimal('salary', 12, 2)
                ->nullable()
                ->after('gross_required')
                ->comment('Employee basic salary at time of overtime request');

            $table->decimal('half_salary', 12, 2)
                ->nullable()
                ->after('salary')
                ->comment('Half of employee salary (salary cap for gross overtime)');

            $table->decimal('net_pay', 12, 2)
                ->nullable()
                ->after('half_salary')
                ->comment('Net overtime amount after tax (what employee receives)');

            $table->decimal('overtime_daily_rate', 10, 2)
                ->nullable()
                ->after('net_pay')
                ->comment('Daily overtime rate used for this request');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn([
                'tax',
                'gross_pay',
                'gross_required',
                'salary',
                'half_salary',
                'net_pay',
                'overtime_daily_rate',
            ]);
        });
    }
}


