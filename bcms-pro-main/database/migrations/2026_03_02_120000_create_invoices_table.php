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
         Schema::connection('bcmis2')->create('invoices', function (Blueprint $table) {
             $table->id();
             $table->string('invoice_number', 100)->index();
             $table->string('batch_number', 50)->index();
             $table->string('pf_number', 50)->index();
             $table->string('employee_name', 200);
             $table->string('customer_bank_account_no', 50)->nullable();
             $table->decimal('amount', 15, 2);
             $table->string('description', 500);
             $table->string('accts_pay_code_combination', 100)->nullable();
             // creator PF number (from auth_user.pf_number)
             $table->string('created_by', 50)->nullable()->index();
             // static invoice metadata
             $table->string('expense_accounts', 100);
             $table->string('vendor', 100);
             $table->string('vendor_site_id', 100);
             $table->string('terms_id', 100);
             $table->unsignedBigInteger('bank_id')->index();
             $table->timestamps();

             $table->foreign('batch_number')
                 ->references('batch_number')
                 ->on('overtime_batches');

             $table->foreign('bank_id')
                 ->references('bank_id')
                 ->on('bank');

             $table->unique(['batch_number', 'pf_number'], 'invoices_unique_batch_employee');
         });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('bcmis2')->dropIfExists('invoices');
    }
};


