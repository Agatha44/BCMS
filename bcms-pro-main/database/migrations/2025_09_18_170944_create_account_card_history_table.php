<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountCardHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_card_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->comment('Reference to account table');
            $table->string('card_reference', 100)->nullable()->comment('Card reference number that was linked/unlinked');
            $table->string('action', 50)->comment('Action performed: linked, unlinked, updated');
            $table->string('old_card_reference', 100)->nullable()->comment('Previous card reference (for updates)');
            $table->string('new_card_reference', 100)->nullable()->comment('New card reference (for updates)');
            $table->string('reason', 255)->nullable()->comment('Reason for the change');
            $table->bigInteger('performed_by')->comment('User ID who performed the action');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('account_id');
            $table->index('card_reference');
            $table->index('action');
            $table->index('performed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_card_history');
    }
}
