<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->index(
                    ['verification_url', 'rctnum', 'dc', 'created_at'],
                    'idx_transactions_bridge_status_scan'
                );
                $table->index(
                    ['verification_url', 'ack_code'],
                    'idx_transactions_bridge_status_ack'
                );
            });
        }

        if (Schema::hasTable('bridge_bills')) {
            Schema::table('bridge_bills', function (Blueprint $table): void {
                $table->index(
                    ['contr_num', 'bill_cancel_date', 'is_cancelled', 'bill_gen_at'],
                    'idx_bridge_bills_bridge_status_gepg'
                );
                $table->index(
                    ['erp_status', 'payment_date'],
                    'idx_bridge_bills_bridge_status_paydate'
                );
                $table->index(
                    ['erp_status', 'receipt_date'],
                    'idx_bridge_bills_bridge_status_receiptdate'
                );
                $table->index(
                    ['erp_status', 'bill_gen_at'],
                    'idx_bridge_bills_bridge_status_billgen'
                );
            });
        }

        if (Schema::hasTable('received_payments')) {
            Schema::table('received_payments', function (Blueprint $table): void {
                $table->index(
                    ['erp_status', 'payment_date'],
                    'idx_received_payments_bridge_status_erp_payment'
                );
            });
        }

        if (Schema::hasTable('bundle_subscriptions')) {
            Schema::table('bundle_subscriptions', function (Blueprint $table): void {
                $table->index(
                    ['status', 'expire_date'],
                    'idx_bundle_subscriptions_status_expire_date'
                );
            });

            if (Schema::hasColumn('bundle_subscriptions', 'expiry_notification')) {
                Schema::table('bundle_subscriptions', function (Blueprint $table): void {
                    $table->index(
                        ['status', 'expire_date', 'expiry_notification'],
                        'idx_bundle_subscriptions_status_expire_notification'
                    );
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table): void {
                $table->dropIndex('idx_transactions_bridge_status_scan');
                $table->dropIndex('idx_transactions_bridge_status_ack');
            });
        }

        if (Schema::hasTable('bridge_bills')) {
            Schema::table('bridge_bills', function (Blueprint $table): void {
                $table->dropIndex('idx_bridge_bills_bridge_status_gepg');
                $table->dropIndex('idx_bridge_bills_bridge_status_paydate');
                $table->dropIndex('idx_bridge_bills_bridge_status_receiptdate');
                $table->dropIndex('idx_bridge_bills_bridge_status_billgen');
            });
        }

        if (Schema::hasTable('received_payments')) {
            Schema::table('received_payments', function (Blueprint $table): void {
                $table->dropIndex('idx_received_payments_bridge_status_erp_payment');
            });
        }

        if (Schema::hasTable('bundle_subscriptions')) {
            Schema::table('bundle_subscriptions', function (Blueprint $table): void {
                $table->dropIndex('idx_bundle_subscriptions_status_expire_date');
            });

            if (Schema::hasColumn('bundle_subscriptions', 'expiry_notification')) {
                Schema::table('bundle_subscriptions', function (Blueprint $table): void {
                    $table->dropIndex('idx_bundle_subscriptions_status_expire_notification');
                });
            }
        }
    }
};
