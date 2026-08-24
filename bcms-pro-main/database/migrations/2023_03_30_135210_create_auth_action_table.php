<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthActionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('auth_action', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('parent_id')->nullable();
			$table->string('title', 100);
			$table->string('controller_id', 50)->nullable();
			$table->string('action_id', 50)->nullable();
			$table->string('route', 100)->nullable();
			$table->string('menu_icon', 50)->nullable();
			$table->boolean('on_menu')->default(0);
			$table->integer('order_no')->nullable();
			$table->boolean('is_active')->default(1);
			$table->timestamps(6);
			$table->integer('created_by')->nullable();
			$table->integer('updated_by')->nullable();
			
			$table->foreign('parent_id', 'auth_action_parent_id_foreign')->references('id')->on('auth_action')->onUpdate('RESTRICT')->onDelete('SET NULL');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('auth_action');
	}

}






































