<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->string('approval_document_path', 500)->nullable()->after('narration')
                ->comment('Path relative to configured storage disk');
            $table->string('approval_document_name', 255)->nullable()->after('approval_document_path');
            $table->string('approval_document_mime', 100)->nullable()->after('approval_document_name');

            $table->bigInteger('approved_by')->nullable()->after('approval_document_mime');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->string('approval_comment', 500)->nullable()->after('approved_at');

            $table->bigInteger('rejected_by')->nullable()->after('approval_comment');
            $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_reason', 500)->nullable()->after('rejected_at');

            $table->bigInteger('returned_by')->nullable()->after('rejection_reason');
            $table->dateTime('returned_at')->nullable()->after('returned_by');
            $table->string('return_comment', 500)->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->dropColumn([
                'approval_document_path',
                'approval_document_name',
                'approval_document_mime',
                'approved_by',
                'approved_at',
                'approval_comment',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'returned_by',
                'returned_at',
                'return_comment',
            ]);
        });
    }
};
