<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('bcmis2')->table('bridge_employee_role', function (Blueprint $table) {
            $table->date('from_date')->nullable()->after('role_id');
            $table->date('to_date')->nullable()->after('from_date');

            $table->index('from_date', 'bridge_employee_role_from_date_index');
            $table->index('to_date', 'bridge_employee_role_to_date_index');
            $table->index(['is_active', 'to_date'], 'bridge_employee_role_is_active_to_date_index');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('bridge_employee_role', function (Blueprint $table) {
            $table->dropIndex('bridge_employee_role_from_date_index');
            $table->dropIndex('bridge_employee_role_to_date_index');
            $table->dropIndex('bridge_employee_role_is_active_to_date_index');
            $table->dropColumn(['from_date', 'to_date']);
        });
    }
};

