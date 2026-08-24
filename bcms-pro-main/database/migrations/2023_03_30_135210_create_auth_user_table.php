<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthUserTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('auth_user', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->string('nida', 50)->nullable();
			$table->string('first_name', 50);
			$table->string('middle_name', 50)->nullable();
			$table->string('surname', 50);
			$table->string('username', 25)->unique('username');
			$table->string('phone', 50)->unique('phone')->comment('users phone number');
			$table->string('email', 100)->nullable()->unique('email')->comment('users email for different communications');
			$table->string('password_hash', 100)->comment('password for user authentication');
			$table->string('password_reset_token', 200)->nullable()->comment('will be used for reseting user password on case forgotten');
			$table->boolean('status')->default(0);
			$table->dateTime('last_login')->nullable();
			$table->string('created_by', 25)->nullable();
			$table->timestamps(6);
			$table->bigInteger('updated_by')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('auth_user');
	}

}
