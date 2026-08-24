<?php

namespace App\Http\Controllers\OverloadFine;

use App\Http\Controllers\BasicController;
use App\Models\OverloadFine;
use App\Helpers\GePG;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;
use Mpdf\Mpdf;

class OverloadFineController extends BasicController
{
    /**
     * Get all overload fines with pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function query(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $status = $request->get('status', ''); // all, paid, unpaid
            $sortBy = $request->get('sort_by', 'bill_gen_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate pagination parameters
            $perPage = max(1, min(100, (int)$perPage)); // Between 1 and 100
            $page = max(1, (int)$page);

            // Start building query
            $query = OverloadFine::query();

            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('middle_name', 'like', "%{$search}%")
                      ->orWhere('surname', 'like', "%{$search}%")
                      ->orWhere('vehicle_num', 'like', "%{$search}%")
                      ->orWhere('contr_num', 'like', "%{$search}%")
                      ->orWhere('psp_receipt_num', 'like', "%{$search}%")
                      ->orWhere('pyr_cell_num', 'like', "%{$search}%")
                      ->orWhere('ticket_num', 'like', "%{$search}%");
                });
            }

            // Apply status filter (based on payment receipt)
            if (!empty($status)) {
                switch ($status) {
                    case 'paid':
                        // Paid: payment receipt is not null
                        $query->whereNotNull('psp_receipt_num');
                        break;
                    case 'unpaid':
                        // Not Paid: payment receipt is null
                        $query->whereNull('psp_receipt_num');
                        break;
                }
            }

            // Apply sorting
            $allowedSortFields = ['bill_gen_at', 'created_at', 'bill_amount', 'vehicle_num'];
            if (in_array($sortBy, $allowedSortFields)) {
                $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('bill_gen_at', 'desc');
            }

            // Get paginated results
            $overloads = $query->paginate($perPage, ['*'], 'page', $page);

            $data = collect($overloads->items())->map(function ($overload) {
                $isPaid = $overload->isPaid();
                $isCancelled = $overload->isCancelled();
                $hasFailed = $overload->hasFailed();

                $item = $overload->toArray();
                $item['source'] = $overload->source ?? 'OLF';
                $item['can_repost'] = $hasFailed && !$isCancelled && !$isPaid;
                $item['can_print'] = $isPaid && !empty($overload->psp_receipt_num);
                $item['can_cancel'] = !$isCancelled && !$isPaid;

                return $item;
            });

            return $this->sendResponse([
                'data' => $data,
                'pagination' => [
                    'current_page' => $overloads->currentPage(),
                    'per_page' => $overloads->perPage(),
                    'total' => $overloads->total(),
                    'last_page' => $overloads->lastPage(),
                    'from' => $overloads->firstItem(),
                    'to' => $overloads->lastItem(),
                    'has_more' => $overloads->hasMorePages(),
                ]
            ], 'Overload fines retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching overload fines', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve overload fines', [], 0, 500);
        }
    }

    /**
     * Get overload fine details by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function queryDetails($id): JsonResponse
    {
        try {
            $overload = OverloadFine::find($id);
            
            if (!$overload) {
                return $this->sendError('Overload fine not found', [], 0, 404);
            }
            
            return $this->sendResponse([$overload], 'Overload fine details retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching overload fine details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve overload fine details', [], 0, 500);
        }
    }

    /**
     * Create new overload fine
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:100',
                'middle_name' => 'nullable|string|max:100',
                'surname' => 'required|string|max:100',
                'pyr_cell_num' => 'required|string|max:100',
                'pyr_email' => 'nullable|email|max:100',
                'tin_number' => 'nullable|string|max:100',
                'bill_amount' => 'required|numeric|min:1',
                'ticket_num' => 'nullable|string|max:50',
                'vehicle_num' => 'required|string|max:100',
                'bill_desc' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $user = Auth::user();
            
            // Create overload fine record
            $overload = OverloadFine::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'surname' => $request->surname,
                'pyr_cell_num' => $request->pyr_cell_num,
                'pyr_email' => $request->pyr_email,
                'tin_number' => $request->tin_number,
                'bill_amount' => $request->bill_amount,
                'ticket_num' => $request->ticket_num,
                'vehicle_num' => $request->vehicle_num,
                'bill_desc' => $request->bill_desc ?? 'Overload Fine',
                'bill_gen_by' => $user->id ?? null,
                'is_cancelled' => '0',
                'bill_gen_at' => Carbon::now()->tz('Africa/Dar_es_Salaam'),
                'bill_exp_dt' => Carbon::now()->tz('Africa/Dar_es_Salaam')->addDays(30),
                'collection_office' => 1,
                'receipt_type' => 3,
                'payment_method' => 3,
                'bill_status' => '0'
            ]);

            // Post bill to GePG
            $bill_id = "OLF" . $overload->id;
            $bill_gen_at = Carbon::now()->tz('Africa/Dar_es_Salaam')->format("Y-m-d\TH:i:s");
            $bill_exp_dt = Carbon::now()->tz('Africa/Dar_es_Salaam')->addDays(30)->format("Y-m-d\TH:i:s");
            
            $payer_name = trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->surname);
            $bill_desc = $request->bill_desc ?? 'Overload Fine - ' . $request->vehicle_num;

            $gepgParams = [
                'payment_ref' => $bill_id,
                'amount' => (string)$request->bill_amount,
                'equiv_amount' => (string)$request->bill_amount,
                'bill_desc' => $bill_desc,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $request->vehicle_num,
                'payer_name' => $payer_name,
                'payer_cell' => $request->pyr_cell_num,
                'generated_by' => (string)($user->id ?? 'system'),
                'days_expires_after' => 30,
                'payer_email' => $request->pyr_email ?? 'noreply@nssf.go.tz',
                'bill_gen_date' => $bill_gen_at,
                'bill_exp_date' => $bill_exp_dt
            ];

            $gepgResponse = GePG::postBill($gepgParams);

            // Update overload with GePG response
            if ($gepgResponse['status'] == 'success') {
                $overload->update([
                    'contr_num' => $gepgResponse['control_num'],
                    't_status' => 'SP'
                ]);

                Log::info('Overload fine created and posted to GePG successfully', [
                    'overload_id' => $overload->id,
                    'control_num' => $gepgResponse['control_num']
                ]);

                return $this->sendResponse($overload, 'Overload charge created successfully');
            } else {
                // Bill creation failed, update status
                $overload->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['error_code'] ?? null
                ]);

                Log::warning('Overload fine created but GePG posting failed', [
                    'overload_id' => $overload->id,
                    'gepg_response' => $gepgResponse
                ]);

                return $this->sendError('Bill created but failed to post to GePG', $gepgResponse, 0, 200);
            }

        } catch (\Exception $e) {
            Log::error('Error creating overload fine', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to create overload fine', [], 0, 500);
        }
    }

    /**
     * Update overload fine
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function editOverload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:overload_fine,id',
                'first_name' => 'required|string|max:100',
                'middle_name' => 'nullable|string|max:100',
                'surname' => 'required|string|max:100',
                'pyr_cell_num' => 'required|string|max:100',
                'pyr_email' => 'nullable|email|max:100',
                'tin_number' => 'nullable|string|max:100',
                'bill_amount' => 'required|numeric|min:1',
                'ticket_num' => 'nullable|string|max:50',
                'vehicle_num' => 'required|string|max:100',
                'bill_desc' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $overload = OverloadFine::find($request->id);
            
            if (!$overload) {
                return $this->sendError('Overload fine not found', [], 0, 404);
            }

            // Only allow editing if not paid and not cancelled
            if ($overload->isPaid()) {
                return $this->sendError('Cannot edit paid overload fine', [], 0, 400);
            }

            if ($overload->isCancelled()) {
                return $this->sendError('Cannot edit cancelled overload fine', [], 0, 400);
            }

            $user = Auth::user();

            $overload->update([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'surname' => $request->surname,
                'pyr_cell_num' => $request->pyr_cell_num,
                'pyr_email' => $request->pyr_email,
                'tin_number' => $request->tin_number,
                'bill_amount' => $request->bill_amount,
                'ticket_num' => $request->ticket_num,
                'vehicle_num' => $request->vehicle_num,
                'bill_desc' => $request->bill_desc ?? 'Overload Fine',
                'updated_by' => $user->id ?? null
            ]);

            Log::info('Overload fine updated successfully', [
                'overload_id' => $overload->id,
                'updated_by' => $user->id ?? null
            ]);

            return $this->sendResponse($overload, 'Overload fine updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating overload fine', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to update overload fine', [], 0, 500);
        }
    }

    /**
     * Cancel bill
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function billCancellation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:overload_fine,id',
                'cancel_reason' => 'required|string|max:200'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $overload = OverloadFine::find($request->id);
            
            if (!$overload) {
                return $this->sendError('Overload fine not found', [], 0, 404);
            }

            // Check if already paid
            if ($overload->isPaid()) {
                return $this->sendError('Cannot cancel paid bill', [], 0, 400);
            }

            // Check if already cancelled
            if ($overload->isCancelled()) {
                return $this->sendError('Bill already cancelled', [], 0, 400);
            }

            $user = Auth::user();

            $overload->update([
                'is_cancelled' => '1',
                'cancel_reason' => $request->cancel_reason,
                'bill_cancel_date' => Carbon::now()->tz('Africa/Dar_es_Salaam'),
                'bill_cancel_by' => $user->id ?? null
            ]);

            Log::info('Bill cancelled successfully', [
                'overload_id' => $overload->id,
                'cancelled_by' => $user->id ?? null,
                'reason' => $request->cancel_reason
            ]);

            return $this->sendResponse($overload, 'Bill cancelled successfully');

        } catch (\Exception $e) {
            Log::error('Error cancelling bill', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to cancel bill', [], 0, 500);
        }
    }

    /**
     * Get error reason for failed bills
     *
     * @param int $id
     * @return JsonResponse
     */
    public function viewReason($id): JsonResponse
    {
        try {
            $overload = OverloadFine::find($id);
            
            if (!$overload) {
                return $this->sendError('Overload fine not found', [], 0, 404);
            }

            if (!$overload->error_code || $overload->error_code == '7101') {
                return $this->sendResponse([], 'No error codes found');
            }

            // Parse error codes (semicolon separated)
            $error_codes = str_replace(';', ',', $overload->error_code);
            
            // Get error descriptions from gepg_errors table
            $errors = DB::table('gepg_errors')
                ->whereRaw("error_code IN ($error_codes)")
                ->select('error_code', 'description')
                ->get();

            return $this->sendResponse($errors, 'Error details retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error retrieving error details', [
                'overload_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve error details', [], 0, 500);
        }
    }

    /**
     * Repost failed bill to GePG
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function repostBill(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:overload_fine,id'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $overload = OverloadFine::find($request->id);
            
            if (!$overload) {
                return $this->sendError('Overload fine not found', [], 0, 404);
            }

            // Check if already paid
            if ($overload->isPaid()) {
                return $this->sendError('Cannot repost paid bill', [], 0, 400);
            }

            // Check if cancelled
            if ($overload->isCancelled()) {
                return $this->sendError('Cannot repost cancelled bill', [], 0, 400);
            }

            $user = Auth::user();

            // Repost to GePG
            $bill_id = "OLF" . $overload->id;
            $bill_gen_at = Carbon::now()->tz('Africa/Dar_es_Salaam')->format("Y-m-d\TH:i:s");
            $bill_exp_dt = Carbon::now()->tz('Africa/Dar_es_Salaam')->addDays(30)->format("Y-m-d\TH:i:s");
            
            $payer_name = trim($overload->first_name . ' ' . ($overload->middle_name ?? '') . ' ' . $overload->surname);
            $bill_desc = $overload->bill_desc ?? 'Overload Fine - ' . $overload->vehicle_num;

            $gepgParams = [
                'payment_ref' => $bill_id,
                'amount' => (string)$overload->bill_amount,
                'equiv_amount' => (string)$overload->bill_amount,
                'bill_desc' => $bill_desc,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $overload->vehicle_num,
                'payer_name' => $payer_name,
                'payer_cell' => $overload->pyr_cell_num,
                'generated_by' => (string)($user->id ?? 'system'),
                'days_expires_after' => 30,
                'payer_email' => $overload->pyr_email ?? 'noreply@nssf.go.tz',
                'bill_gen_date' => $bill_gen_at,
                'bill_exp_date' => $bill_exp_dt
            ];

            $gepgResponse = GePG::postBill($gepgParams);

            // Update overload with GePG response
            if ($gepgResponse['status'] == 'success') {
                $overload->update([
                    'contr_num' => $gepgResponse['control_num'],
                    't_status' => 'SP',
                    'error_code' => null,
                    'updated_by' => $user->id ?? null
                ]);

                Log::info('Bill reposted to GePG successfully', [
                    'overload_id' => $overload->id,
                    'control_num' => $gepgResponse['control_num']
                ]);

                return $this->sendResponse($overload, 'Bill reposted successfully');
            } else {
                // Repost failed
                $overload->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['error_code'] ?? null,
                    'updated_by' => $user->id ?? null
                ]);

                Log::warning('Bill repost to GePG failed', [
                    'overload_id' => $overload->id,
                    'gepg_response' => $gepgResponse
                ]);

                return $this->sendError('Failed to repost bill to GePG', $gepgResponse, 0, 200);
            }

        } catch (\Exception $e) {
            Log::error('Error reposting bill', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to repost bill', [], 0, 500);
        }
    }

    /**
     * Print overload fine receipt as PDF
     *
     * @param int $id
     * @return Response
     */
    public function printReceipt($id): Response
    {
        try {
            $overload = OverloadFine::find($id);
            
            if (!$overload) {
                return response('Overload fine not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            // Check if overload has been paid
            if (empty($overload->psp_receipt_num)) {
                return response('Receipt not available. Overload fine has not been paid.', 400)
                    ->header('Content-Type', 'text/html');
            }

            // Prepare receipt data
            $amount = $overload->paid_amt ?? $overload->bill_amount;
            $payer_name = trim($overload->first_name . ' ' . ($overload->middle_name ?? '') . ' ' . $overload->surname);
            
            $receipt_data = [
                'receipt_number' => $overload->receipt_number ?? $overload->contr_num ?? 'N/A',
                'payment_date' => $overload->payment_date ?? $overload->bill_gen_at,
                'psp_receipt_num' => $overload->psp_receipt_num,
                'amount' => $amount,
                'amount_word' => $this->numberToWords($amount) . ' Shillings Only',
                'trx_dt_tm' => $overload->trx_dt_tm ?? $overload->payment_date ?? $overload->bill_gen_at,
                'vehicle_num' => $overload->vehicle_num,
                'payer_name' => $payer_name,
                'bill_desc' => $overload->bill_desc ?? 'Overload Fine',
                'control_num' => $overload->contr_num,
                'pay_ref_id' => $overload->pay_ref_id,
                'ticket_num' => $overload->ticket_num,
            ];

            // Get logo and convert to base64 for mPDF
            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';
            
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }
            
            // Generate HTML from blade template (we'll use incident receipt template as base)
            $html = View::make('receipts.incident_receipt', compact('receipt_data', 'logoBase64'))->render();

            // Setup mPDF
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 10,
                'margin_footer' => 10,
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15
            ]);

            // Set watermark before generating PDF
            if (file_exists($logoPath)) {
                $mpdf->SetWatermarkImage($logoPath, 0.05);
                $mpdf->showWatermarkImage = true;
            }

            // Generate PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF (inline display)
            return response($mpdf->Output('overload_receipt_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="overload_receipt_' . $id . '.pdf"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating overload receipt PDF', [
                'overload_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response('Failed to generate receipt: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/html');
        }
    }

    /**
     * Convert number to words (Tanzanian Shillings)
     */
    private function numberToWords($number)
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        
        if ($number == 0) return 'Zero';
        if ($number < 10) return $ones[$number];
        if ($number < 20) return $teens[$number - 10];
        if ($number < 100) {
            $tens_part = $tens[floor($number / 10)];
            $ones_part = $ones[$number % 10];
            return $tens_part . ($ones_part ? ' ' . $ones_part : '');
        }
        if ($number < 1000) {
            $hundreds = floor($number / 100);
            $remainder = $number % 100;
            return $ones[$hundreds] . ' Hundred' . ($remainder ? ' ' . $this->numberToWords($remainder) : '');
        }
        if ($number < 1000000) {
            $thousands = floor($number / 1000);
            $remainder = $number % 1000;
            return $this->numberToWords($thousands) . ' Thousand' . ($remainder ? ' ' . $this->numberToWords($remainder) : '');
        }
        if ($number < 1000000000) {
            $millions = floor($number / 1000000);
            $remainder = $number % 1000000;
            return $this->numberToWords($millions) . ' Million' . ($remainder ? ' ' . $this->numberToWords($remainder) : '');
        }
        return 'Number too large';
    }
}
