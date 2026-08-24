<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_engine_module_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bridge_module_id')->nullable()->index();
            $table->string('menu_label')->default('Reports');
            $table->string('route_slug')->unique();
            $table->string('module_path_prefix');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_engine_module_registrations');
    }
};
