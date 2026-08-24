<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthRolePermissionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('auth_role_permission', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('permission_id')->unique('permission_id');
			$table->integer('role_id');
			$table->boolean('is_active')->default(1);
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('auth_role_permission');
	}

}
