<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthRoleActionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('auth_role_action', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('role_id');
			$table->integer('action_id');
			$table->boolean('is_active')->default(1);
			$table->timestamps(6);
			$table->integer('created_by')->nullable();
			$table->integer('updated_by')->nullable();
			
			$table->foreign('role_id', 'auth_role_action_role_id_foreign')->references('id')->on('auth_role')->onUpdate('RESTRICT')->onDelete('CASCADE');
			$table->foreign('action_id', 'auth_role_action_action_id_foreign')->references('id')->on('auth_action')->onUpdate('RESTRICT')->onDelete('CASCADE');
			$table->unique(['role_id', 'action_id'], 'role_id_action_id_unique');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('auth_role_action');
	}

}






































