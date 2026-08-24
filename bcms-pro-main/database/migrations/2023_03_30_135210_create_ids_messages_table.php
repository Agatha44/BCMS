<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIdsMessagesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('ids_messages', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('sms_source', 200)->nullable();
			$table->string('sms_recipient', 200)->nullable();
			$table->string('sms_body', 500)->nullable();
			$table->integer('status')->nullable()->default(0);
			$table->string('sms_process', 200)->nullable();
			$table->dateTime('created_at')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('ids_messages');
	}

}
