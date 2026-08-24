<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFcmTokenToAuthUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auth_user', function (Blueprint $table) {
            $table->text('fcm_token')->nullable()->after('email')->comment('Firebase Cloud Messaging token for push notifications');
            $table->string('device_type', 20)->nullable()->after('fcm_token')->comment('Device type: android, ios, web');
            $table->timestamp('fcm_token_updated_at')->nullable()->after('device_type')->comment('Last time FCM token was updated');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auth_user', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'device_type', 'fcm_token_updated_at']);
        });
    }
}

