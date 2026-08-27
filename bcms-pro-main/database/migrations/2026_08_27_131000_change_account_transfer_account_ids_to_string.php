<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_transfer')) {
            return;
        }

        DB::statement('ALTER TABLE account_transfer MODIFY from_account_id VARCHAR(32) NOT NULL');
        DB::statement('ALTER TABLE account_transfer MODIFY to_account_id VARCHAR(32) NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('account_transfer')) {
            return;
        }

        DB::statement('ALTER TABLE account_transfer MODIFY from_account_id BIGINT NOT NULL');
        DB::statement('ALTER TABLE account_transfer MODIFY to_account_id BIGINT NOT NULL');
    }
};
