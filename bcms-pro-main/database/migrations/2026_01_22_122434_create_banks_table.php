<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bank', function (Blueprint $table) {
            $table->id('bank_id')->comment('Unique bank identifier');
            $table->string('bank_name', 255)->comment('Full bank name');
            $table->string('short_name', 100)->comment('Short name or abbreviation');
            $table->string('sort_code', 50)->nullable()->comment('Bank sort code');
            $table->string('bank_code', 50)->comment('Bank code');
            $table->string('bi_code', 50)->comment('BI code');
            $table->boolean('is_active')->default(true)->comment('Active status');
            $table->string('erp_bc', 50)->nullable()->comment('ERP BC code');
            $table->string('erp_br', 50)->nullable()->comment('ERP BR code');
            $table->string('citi_code', 50)->nullable()->comment('Citi code');
            $table->string('swift_code', 50)->nullable()->comment('SWIFT code');
            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes for better performance
            $table->index('bank_name');
            $table->index('bank_code');
            $table->index('bi_code');
            $table->index('is_active');
            $table->index('swift_code');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bank');
    }
};
