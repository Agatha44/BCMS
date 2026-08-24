<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalizes image column to VARCHAR(500) for on-disk file paths.
 * Upgrades older TEXT/LONGTEXT definitions from earlier iterations.
 */
class ChangeTollCaptureImageToLongtext extends Migration
{
    public function up()
    {
        $this->normalizeImageColumn('toll_capture');
        $this->normalizeImageColumn('toll_capture_history');
    }

    public function down()
    {
        if (Schema::hasTable('toll_capture')) {
            DB::statement('ALTER TABLE toll_capture MODIFY image TEXT NULL');
        }

        if (Schema::hasTable('toll_capture_history')) {
            DB::statement('ALTER TABLE toll_capture_history MODIFY image TEXT NULL');
        }
    }

    private function normalizeImageColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'image')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'image']
        );

        if (! $column) {
            return;
        }

        $type = strtolower($column->data_type);
        $length = (int) ($column->max_length ?? 0);

        if ($type !== 'varchar' || $length !== 500) {
            DB::statement("ALTER TABLE {$table} MODIFY image VARCHAR(500) NULL");
        }
    }
}
