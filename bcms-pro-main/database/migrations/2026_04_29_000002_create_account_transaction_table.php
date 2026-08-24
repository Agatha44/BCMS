<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountTransactionTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('account_transaction', function (Blueprint $table) {
			$table->bigInteger('id', true);
			$table->bigInteger('account_transfer_id')->comment('Reference to account_transfer table');
			$table->bigInteger('account_id')->comment('Reference to account table');

			$table->string('entry_type', 10)->comment('debit|credit');
			$table->decimal('amount', 15, 2)->comment('Signed: debit negative, credit positive');

			$table->decimal('previous_balance', 15, 2)->nullable();
			$table->decimal('new_balance', 15, 2)->nullable();

			$table->string('reference_number', 100)->nullable();
			$table->string('description', 255)->nullable();
			$table->json('metadata')->nullable();

			$table->bigInteger('created_by')->nullable();
			$table->timestamps(6);
			$table->bigInteger('updated_by')->nullable();

			$table->index('account_id', 'account_transaction_account_id');
			$table->index('account_transfer_id', 'account_transaction_account_transfer_id');
			$table->index('entry_type', 'account_transaction_entry_type');
			$table->index('created_at', 'account_transaction_created_at');
			$table->unique(['account_transfer_id', 'entry_type'], 'account_transaction_transfer_entry_type');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('account_transaction');
	}
}

