<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('account', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->string('account_no', 50)->nullable()->unique('account_no');
			$table->string('nida', 100)->nullable();
			$table->integer('control_no')->nullable()->default(0);
			$table->float('account_balance', 22, 0)->nullable()->default(0);
			$table->float('amount_received', 10, 0)->nullable()->default(0);
			$table->string('first_name', 50);
			$table->string('middle_name', 100)->nullable();
			$table->string('surname', 50);
			$table->string('phone', 50)->unique('phone')->comment('users phone number');
			$table->string('email', 100)->comment('users email for different communications');
			$table->string('password_hash', 100)->nullable()->comment('password for user authentication');
			$table->dateTime('otp_cdate')->nullable();
			$table->string('one_time_password', 200)->nullable()->comment('will be used for reseting user password on case forgotten');
			$table->dateTime('last_login')->nullable();
			$table->string('status', 15)->nullable()->default('1');
			$table->string('created_by', 50)->nullable();
			$table->timestamps(6);
			$table->string('updated_by', 30)->nullable();
			$table->integer('otp_status')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('account');
	}

}
