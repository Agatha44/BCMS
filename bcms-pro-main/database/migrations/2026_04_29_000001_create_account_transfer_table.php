<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountTransferTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('account_transfer', function (Blueprint $table) {
			$table->bigInteger('id', true);
			$table->uuid('transfer_uuid')->unique('transfer_uuid');
			$table->string('client_reference', 64)->nullable();

			$table->bigInteger('from_account_id')->comment('Account being debited');
			$table->bigInteger('to_account_id')->comment('Account being credited');

			$table->decimal('amount', 15, 2)->comment('Transfer amount');
			$table->string('status', 20)->default('posted')->comment('posted|failed|reversed|pending');
			$table->dateTime('posted_at')->nullable();
			$table->string('narration', 255)->nullable();

			$table->bigInteger('created_by')->nullable();
			$table->timestamps(6);
			$table->bigInteger('updated_by')->nullable();

			$table->index('from_account_id', 'account_transfer_from_account_id');
			$table->index('to_account_id', 'account_transfer_to_account_id');
			$table->index('status', 'account_transfer_status');
			$table->index('created_at', 'account_transfer_created_at');
			$table->unique(['from_account_id', 'client_reference'], 'account_transfer_from_client_reference');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::dropIfExists('account_transfer');
	}
}

