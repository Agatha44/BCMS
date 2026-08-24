<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveRoleIdFromBridgeShiftsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('bridge_shifts', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex('bridge_shifts_role_id_idx');
            // Then drop the column
            $table->dropColumn('role_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('bridge_shifts', function (Blueprint $table) {
            // Restore the column
            $table->integer('role_id')->after('id');
            // Restore the index
            $table->index('role_id', 'bridge_shifts_role_id_idx');
        });
    }
}
