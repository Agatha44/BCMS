<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrfVotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('prf_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prf_detail_id')->comment('Reference to overtime_prf_details.id');
            $table->string('vote_id', 255)->comment('Unique vote identifier from Budget API (e.g., 10.104010.5024430000.1040501130.000000)');
            $table->decimal('amount', 15, 2)->comment('Amount allocated to this budget segment');
            $table->string('segment3_desc', 500)->nullable()->comment('Description of the budget segment');
            $table->timestamps();

            // Indexes
            $table->index('prf_detail_id');
            $table->index('vote_id');
            $table->index('amount');

            // Foreign key constraint
            $table->foreign('prf_detail_id')
                ->references('id')
                ->on('overtime_prf_details')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Unique constraint: one vote_id per prf_detail_id
            $table->unique(['prf_detail_id', 'vote_id'], 'unique_prf_vote');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('prf_votes');
    }
}
