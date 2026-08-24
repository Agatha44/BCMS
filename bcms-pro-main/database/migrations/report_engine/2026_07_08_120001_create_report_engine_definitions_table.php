<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_engine_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_registration_id')
                ->constrained('report_engine_module_registrations')
                ->cascadeOnDelete();
            $table->string('category_key');
            $table->string('name');
            $table->string('key');
            $table->text('description')->nullable();
            $table->string('script');
            $table->string('handler');
            $table->json('params')->nullable();
            $table->json('output_columns')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['module_registration_id', 'key']);
            $table->unique(['module_registration_id', 'script']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_engine_definitions');
    }
};
