<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthUserRoleTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('auth_user_role', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('user_id');
			$table->integer('role_id');
			$table->boolean('is_active')->default(1);
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
			$table->unique(['role_id','user_id'], 'user_id_role_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('auth_user_role');
	}

}
