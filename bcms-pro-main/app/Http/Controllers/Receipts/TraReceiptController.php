<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\BridgeBill;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TraReceiptController extends Controller
{
    /**
     * Get TRA receipt by bill ID
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTraReceipt(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|string',
            'control_number' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $billId = $request->input('bill_id');
            $controlNumber = $request->input('control_number');
            $bridgeBill = null;
            $topUpBill = null;

            // Step 1: If control_number is provided, check top_up table first
            if ($controlNumber) {
                $topUpBill = \DB::table('top_up')
                    ->select('receipt_number', 'payer_name', 'payment_date', 'contr_num')
                    ->where('id', $billId)
                    ->where('contr_num', $controlNumber)
                    ->first();
            }

            // Step 2: If not found in top_up or no control_number provided, check bridge_bills
            if (!$topUpBill) {
                $bridgeBill = BridgeBill::select('receipt_number', 'payer_name', 'payment_date', 'contr_num')
                    ->find($billId);
                
                if (!$bridgeBill) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bill not found in either top_up or bridge_bills table'
                    ], 404);
                }
            }

            // Use the found bill (either from top_up or bridge_bills)
            $bill = $topUpBill ?: $bridgeBill;

            // Check if bill has a receipt_number
            if (!$bill->receipt_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'No receipt number found for this bill'
                ], 404);
            }

            // Step 3: Find transaction by receipt_number with only required fields
            $transaction = Transaction::select('verification_url')
                ->where('receipt_num', $bill->receipt_number)
                ->first();
            
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found for receipt number: ' . $bill->receipt_number
                ], 404);
            }

            if (!$transaction->verification_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'No verification URL found for this transaction'
                ], 404);
            }

            // Return only essential data quickly
            return response()->json([
                'success' => true,
                'message' => 'TRA receipt data retrieved successfully',
                'data' => [
                    'verification_url' => $transaction->verification_url,
                    'receipt_number' => $bill->receipt_number,
                    'payer_name' => $bill->payer_name,
                    'payment_date' => $bill->payment_date,
                    'control_number' => $bill->contr_num,
                    'bill_source' => $topUpBill ? 'top_up' : 'bridge_bills'
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving TRA receipt data', [
                'bill_id' => $request->input('bill_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the TRA receipt data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
