<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->enum('request_type', ['registration', 'exemption', 'update']);
            $table->string('plate_number');
            $table->unsignedBigInteger('body_type_id')->nullable();
            $table->string('body_type_name')->nullable();
            $table->string('owner_name');
            $table->string('owner_phone');
            $table->string('owner_email')->nullable();
            $table->string('nida_number')->nullable();
            $table->text('exemption_reason')->nullable();
            $table->json('update_details')->nullable(); // Store old and new values for updates
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('submitted_at')->useCurrent();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['request_type', 'status']);
            $table->index(['plate_number']);
            $table->index(['submitted_by']);
            $table->index(['reviewed_by']);
            $table->index(['submitted_at']);

            // Foreign keys
            $table->foreign('body_type_id')->references('id')->on('body_type')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_requests');
    }
};
