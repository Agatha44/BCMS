<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVfdRegistrationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('vfd_registration', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('ack_code')->nullable();
			$table->string('reg_id', 25)->nullable();
			$table->string('serila', 25)->nullable();
			$table->string('uin', 100)->nullable();
			$table->string('tin', 50)->nullable();
			$table->string('vrn', 50)->nullable();
			$table->string('mobile', 25)->nullable();
			$table->string('address', 50)->nullable();
			$table->string('street', 50)->nullable();
			$table->string('city', 30)->nullable();
			$table->string('country', 50)->nullable();
			$table->string('name', 50)->nullable();
			$table->string('receiptcode', 25)->nullable();
			$table->string('region', 25)->nullable();
			$table->string('routingkey', 25)->nullable();
			$table->string('gc', 25)->nullable();
			$table->string('taxoffice', 50)->nullable();
			$table->string('username', 50)->nullable();
			$table->string('password', 100)->nullable();
			$table->string('tokenpath', 50)->nullable();
			$table->integer('taxcodea')->nullable();
			$table->integer('taxcodeb')->nullable();
			$table->integer('taxcodec')->nullable();
			$table->integer('taxcoded')->nullable();
			$table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->integer('created_by')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('vfd_registration');
	}

}
