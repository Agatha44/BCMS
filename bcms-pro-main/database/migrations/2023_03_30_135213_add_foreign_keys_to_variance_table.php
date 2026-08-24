<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToVarianceTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('variance', function(Blueprint $table)
		{
			$table->foreign('shift', 'variance_shift_id_fk')->references('id')->on('shift')->onUpdate('NO ACTION')->onDelete('NO ACTION');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('variance', function(Blueprint $table)
		{
			$table->dropForeign('variance_shift_id_fk');
		});
	}

}
