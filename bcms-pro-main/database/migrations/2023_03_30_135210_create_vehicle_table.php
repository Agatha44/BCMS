<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('vehicle', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('plate_no', 50)->unique('plate_no');
			$table->string('rfid_tag_no', 50)->default('0')->index('idx_vehicle_rfid_tag_no');
			$table->string('account_no', 25)->nullable()->index('idx_vehicle_account_no');
			$table->integer('body_type_id')->nullable();
			$table->integer('created_by')->nullable()->index('idx_vehicle_created_by');
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
			$table->string('exempted', 50)->nullable()->index('idx_vehicle_exempted');
			$table->string('status', 20)->nullable()->default('1');
			$table->string('exempt_reason', 25)->nullable();
			$table->string('image', 500)->nullable();
			$table->integer('rfid_tag_status')->nullable();
			$table->integer('card_number_status')->nullable();
			$table->string('card_number', 150)->nullable()->unique('vehicle_card_number_uindex');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('vehicle');
	}

}
