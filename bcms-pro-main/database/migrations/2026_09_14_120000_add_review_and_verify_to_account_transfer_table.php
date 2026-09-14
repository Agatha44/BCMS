<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->bigInteger('reviewed_by')->nullable()->after('approval_document_mime');
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_comment', 500)->nullable()->after('reviewed_at');

            $table->bigInteger('verified_by')->nullable()->after('review_comment');
            $table->dateTime('verified_at')->nullable()->after('verified_by');
            $table->string('verification_comment', 500)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->dropColumn([
                'reviewed_by',
                'reviewed_at',
                'review_comment',
                'verified_by',
                'verified_at',
                'verification_comment',
            ]);
        });
    }
};
