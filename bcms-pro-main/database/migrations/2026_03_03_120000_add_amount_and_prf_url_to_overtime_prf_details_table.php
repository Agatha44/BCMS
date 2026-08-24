<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAmountAndPrfUrlToOvertimePrfDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('overtime_prf_details', function (Blueprint $table) {
            // Total amount for the PRF/batch
            if (!Schema::connection('bcmis2')->hasColumn('overtime_prf_details', 'amount')) {
                $table->decimal('amount', 18, 2)->nullable()->after('payment_reference');
            }

            // Direct URL for the PRF document (if Budget API provides it)
            if (!Schema::connection('bcmis2')->hasColumn('overtime_prf_details', 'prf_url')) {
                $table->text('prf_url')->nullable()->after('prf_response_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('overtime_prf_details', function (Blueprint $table) {
            if (Schema::connection('bcmis2')->hasColumn('overtime_prf_details', 'prf_url')) {
                $table->dropColumn('prf_url');
            }
            if (Schema::connection('bcmis2')->hasColumn('overtime_prf_details', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }
}


