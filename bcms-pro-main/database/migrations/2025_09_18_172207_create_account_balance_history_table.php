<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountBalanceHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_balance_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->comment('Reference to account table');
            $table->string('card_reference', 100)->nullable()->comment('Card reference used for transaction');
            $table->string('transaction_type', 50)->comment('Type: deduction, top_up, adjustment');
            $table->decimal('previous_balance', 15, 2)->comment('Balance before transaction');
            $table->decimal('transaction_amount', 15, 2)->comment('Amount deducted or added');
            $table->decimal('new_balance', 15, 2)->comment('Balance after transaction');
            $table->string('lane_number', 20)->nullable()->comment('Lane/POS terminal that processed transaction');
            $table->string('terminal_id', 50)->nullable()->comment('POS terminal identifier');
            $table->string('reference_number', 100)->nullable()->comment('Transaction reference number');
            $table->string('description', 255)->nullable()->comment('Transaction description');
            $table->json('metadata')->nullable()->comment('Additional transaction data (JSON)');
            $table->bigInteger('processed_by')->nullable()->comment('User/system that processed transaction');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('account_id');
            $table->index('card_reference');
            $table->index('transaction_type');
            $table->index('lane_number');
            $table->index('terminal_id');
            $table->index('reference_number');
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
        Schema::dropIfExists('account_balance_history');
    }
}
