<?php

/**
 * Test script to demonstrate the new relationship between 
 * account_balance_history and toll_transaction tables
 * 
 * This script shows how to:
 * 1. Query balance history with toll transaction data
 * 2. Query toll transactions with balance history data
 * 3. Access related data through Eloquent relationships
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AccountBalanceHistory;
use App\Models\TollTransaction;
use App\Models\Account;

echo "=== Testing Account Balance History and Toll Transaction Relationship ===\n\n";

try {
    // Test 1: Get balance history with toll transaction data
    echo "1. Testing: Get balance history with toll transaction data\n";
    echo "------------------------------------------------------------\n";
    
    $balanceHistory = AccountBalanceHistory::with([
        'account:id,account_no,first_name,surname',
        'tollTransaction:id,receipt_num,charged_amount,trans_type,created_at'
    ])
    ->whereNotNull('transaction_id')
    ->first();
    
    if ($balanceHistory) {
        echo "Balance History ID: " . $balanceHistory->id . "\n";
        echo "Account: " . $balanceHistory->account->full_name . " (" . $balanceHistory->account->account_no . ")\n";
        echo "Transaction Type: " . $balanceHistory->transaction_type . "\n";
        echo "Amount: " . $balanceHistory->transaction_amount . "\n";
        echo "Reference: " . $balanceHistory->reference_number . "\n";
        
        if ($balanceHistory->tollTransaction) {
            echo "Linked Toll Transaction:\n";
            echo "  - ID: " . $balanceHistory->tollTransaction->id . "\n";
            echo "  - Receipt: " . $balanceHistory->tollTransaction->receipt_num . "\n";
            echo "  - Amount: " . $balanceHistory->tollTransaction->charged_amount . "\n";
            echo "  - Type: " . $balanceHistory->tollTransaction->trans_type . "\n";
        } else {
            echo "No linked toll transaction found\n";
        }
    } else {
        echo "No balance history records with transaction_id found\n";
    }
    
    echo "\n";
    
    // Test 2: Get toll transaction with balance history data
    echo "2. Testing: Get toll transaction with balance history data\n";
    echo "------------------------------------------------------------\n";
    
    $tollTransaction = TollTransaction::with([
        'accountBalanceHistories:id,account_id,transaction_id,transaction_type,transaction_amount,reference_number'
    ])
    ->where('trans_type', 'CASHLESS')
    ->first();
    
    if ($tollTransaction) {
        echo "Toll Transaction ID: " . $tollTransaction->id . "\n";
        echo "Receipt: " . $tollTransaction->receipt_num . "\n";
        echo "Account: " . $tollTransaction->account_no . "\n";
        echo "Amount: " . $tollTransaction->charged_amount . "\n";
        echo "Type: " . $tollTransaction->trans_type . "\n";
        
        if ($tollTransaction->accountBalanceHistories->count() > 0) {
            echo "Linked Balance History Records:\n";
            foreach ($tollTransaction->accountBalanceHistories as $history) {
                echo "  - ID: " . $history->id . "\n";
                echo "    Type: " . $history->transaction_type . "\n";
                echo "    Amount: " . $history->transaction_amount . "\n";
                echo "    Reference: " . $history->reference_number . "\n";
            }
        } else {
            echo "No linked balance history records found\n";
        }
    } else {
        echo "No CASHLESS toll transactions found\n";
    }
    
    echo "\n";
    
    // Test 3: Query balance history by toll transaction ID
    echo "3. Testing: Query balance history by toll transaction ID\n";
    echo "------------------------------------------------------------\n";
    
    $tollTransactionId = 1; // Replace with actual ID
    $balanceHistoryByToll = AccountBalanceHistory::where('transaction_id', $tollTransactionId)->first();
    
    if ($balanceHistoryByToll) {
        echo "Found balance history for toll transaction ID: " . $tollTransactionId . "\n";
        echo "Balance History ID: " . $balanceHistoryByToll->id . "\n";
        echo "Account: " . $balanceHistoryByToll->account->full_name . "\n";
        echo "Amount: " . $balanceHistoryByToll->transaction_amount . "\n";
    } else {
        echo "No balance history found for toll transaction ID: " . $tollTransactionId . "\n";
    }
    
    echo "\n";
    
    // Test 4: Show statistics
    echo "4. Statistics\n";
    echo "-------------\n";
    
    $totalBalanceHistory = AccountBalanceHistory::count();
    $linkedBalanceHistory = AccountBalanceHistory::whereNotNull('transaction_id')->count();
    $totalTollTransactions = TollTransaction::count();
    $cashlessTollTransactions = TollTransaction::where('trans_type', 'CASHLESS')->count();
    
    echo "Total Balance History Records: " . $totalBalanceHistory . "\n";
    echo "Linked Balance History Records: " . $linkedBalanceHistory . "\n";
    echo "Total Toll Transactions: " . $totalTollTransactions . "\n";
    echo "CASHLESS Toll Transactions: " . $cashlessTollTransactions . "\n";
    echo "Linkage Percentage: " . ($totalBalanceHistory > 0 ? round(($linkedBalanceHistory / $totalBalanceHistory) * 100, 2) : 0) . "%\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== Test Complete ===\n";





















