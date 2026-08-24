<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bundle_subscriptions')) {
            return;
        }

        Schema::table('bundle_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('bundle_subscriptions', 'erms_status')) {
                $table->unsignedTinyInteger('erms_status')->nullable()->after('status');
            }
            if (! Schema::hasColumn('bundle_subscriptions', 'erms_submitted_at')) {
                $table->dateTime('erms_submitted_at')->nullable()->after('erms_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bundle_subscriptions')) {
            return;
        }

        Schema::table('bundle_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('bundle_subscriptions', 'erms_submitted_at')) {
                $table->dropColumn('erms_submitted_at');
            }
            if (Schema::hasColumn('bundle_subscriptions', 'erms_status')) {
                $table->dropColumn('erms_status');
            }
        });
    }
};
