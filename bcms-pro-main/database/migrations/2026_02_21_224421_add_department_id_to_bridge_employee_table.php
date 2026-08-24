<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddDepartmentIdToBridgeEmployeeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if column exists, if not add it
        if (!Schema::connection('bcmis2')->hasColumn('bridge_employee', 'department_id')) {
            Schema::connection('bcmis2')->table('bridge_employee', function (Blueprint $table) {
                $table->bigInteger('department_id')->nullable()->after('district_id')->comment('Reference to departments table');
            });
        } else {
            // Column exists, but might be unsigned - need to change it to signed to match departments table
            // Check the column type and alter if needed
            DB::connection('bcmis2')->statement("
                ALTER TABLE `bridge_employee` 
                MODIFY COLUMN `department_id` BIGINT NULL COMMENT 'Reference to departments table'
            ");
        }
        
        // Check if index exists, if not add it
        $sm = Schema::connection('bcmis2')->getConnection()->getDoctrineSchemaManager();
        $indexesFound = $sm->listTableIndexes('bridge_employee');
        if (!isset($indexesFound['bridge_employee_department_id_index'])) {
            Schema::connection('bcmis2')->table('bridge_employee', function (Blueprint $table) {
                $table->index('department_id');
            });
        }
        
        // Add foreign key constraint using raw SQL to avoid errors if it already exists
        $foreignKeyExists = DB::connection('bcmis2')->select("
            SELECT COUNT(*) as count
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'bridge_employee'
            AND CONSTRAINT_NAME = 'bridge_employee_department_id_foreign'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        
        if ($foreignKeyExists[0]->count == 0) {
            DB::connection('bcmis2')->statement("
                ALTER TABLE `bridge_employee`
                ADD CONSTRAINT `bridge_employee_department_id_foreign`
                FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`)
                ON DELETE SET NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('bridge_employee', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropColumn('department_id');
        });
    }
}
