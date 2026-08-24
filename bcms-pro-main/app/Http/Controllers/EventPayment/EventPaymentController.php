<?php

namespace App\Http\Controllers\EventPayment;

use App\Helpers\EnvironmentHelper;
use App\Http\Controllers\BasicController;
use App\Models\EventPayment;
use App\Models\GepgError;
use App\Helpers\GePG;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;
use Mpdf\Mpdf;

class EventPaymentController extends BasicController
{
    /**
     * Get all event payments with pagination
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
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate pagination parameters
            $perPage = max(1, min(100, (int)$perPage));
            $page = max(1, (int)$page);

            // Start building query
            $query = EventPayment::query();

            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('payer_name', 'like', "%{$search}%")
                      ->orWhere('receiver_name', 'like', "%{$search}%")
                      ->orWhere('event_description', 'like', "%{$search}%")
                      ->orWhere('control_num', 'like', "%{$search}%")
                      ->orWhere('psp_receipt_num', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%");
                });
            }

            // Apply status filter (based on payment receipt)
            if (!empty($status)) {
                switch ($status) {
                    case 'paid':
                        $query->whereNotNull('psp_receipt_num');
                        break;
                    case 'unpaid':
                        $query->whereNull('psp_receipt_num');
                        break;
                }
            }

            // Apply sorting
            $allowedSortFields = ['created_at', 'event_date', 'amount', 'payer_name', 'receiver_name'];
            if (in_array($sortBy, $allowedSortFields)) {
                $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Get paginated results
            $payments = $query->paginate($perPage, ['*'], 'page', $page);

            return $this->sendResponse([
                'data' => $payments->items(),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                    'from' => $payments->firstItem(),
                    'to' => $payments->lastItem(),
                    'has_more' => $payments->hasMorePages(),
                ]
            ], 'Event payments retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching event payments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve event payments', [], 0, 500);
        }
    }

    /**
     * Get event payment details by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function queryDetails($id): JsonResponse
    {
        try {
            $payment = EventPayment::find($id);
            
            if (!$payment) {
                return $this->sendError('Event payment not found', [], 0, 404);
            }
            
            return $this->sendResponse([$payment], 'Event payment details retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching event payment details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve event payment details', [], 0, 500);
        }
    }

    /**
     * View reason for failed payment
     *
     * @param int $id
     * @return JsonResponse
     */
    public function viewReason($id): JsonResponse
    {
        try {
            $payment = EventPayment::find($id);
            
            if (!$payment) {
                return $this->sendError('Event payment not found', [], 0, 404);
            }

            if (empty($payment->error_code)) {
                return $this->sendError('No error code available', [], 0, 404);
            }

            // Parse error codes (comma-separated)
            $errorCodes = explode(',', str_replace(';', ',', $payment->error_code));
            $errorCodes = array_map('trim', $errorCodes);
            $errorCodes = array_filter($errorCodes);

            // Get error details from gepg_errors table
            $errors = GepgError::whereIn('error_code', $errorCodes)->get();

            return $this->sendResponse($errors, 'Error reasons retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching event payment error reason', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve error reason', [], 0, 500);
        }
    }

    /**
     * Print event payment receipt as PDF
     *
     * @param int $id
     * @return Response
     */
    public function printReceipt($id): Response
    {
        try {
            $payment = EventPayment::find($id);
            
            if (!$payment) {
                return response('Event payment not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            // Check if payment has been made
            if (empty($payment->psp_receipt_num)) {
                return response('Receipt not available. Payment has not been completed.', 400)
                    ->header('Content-Type', 'text/html');
            }

            // Prepare receipt data
            $amount = $payment->paid_amt ?? $payment->amount;
            $receipt_data = [
                'receipt_number' => $payment->receipt_number ?? $payment->control_num ?? 'N/A',
                'payment_date' => $payment->payment_date ?? $payment->created_at,
                'psp_receipt_num' => $payment->psp_receipt_num,
                'amount' => $amount,
                'amount_word' => $this->numberToWords($amount) . ' Shillings Only',
                'trx_dt_tm' => $payment->trx_dt_tm ?? $payment->payment_date ?? $payment->created_at,
                'payer_name' => $payment->payer_name,
                'receiver_name' => $payment->receiver_name,
                'event_description' => $payment->event_description,
                'event_date' => $payment->event_date,
                'control_num' => $payment->control_num,
                'pay_ref_id' => $payment->pay_ref_id,
            ];

            // Get logo and convert to base64 for mPDF
            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';
            
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }
            
            // Generate HTML from blade template
            $html = View::make('receipts.event_receipt', compact('receipt_data', 'logoBase64'))->render();

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

            // Set watermark
            if (file_exists($logoPath)) {
                $mpdf->SetWatermarkImage($logoPath, 0.05);
                $mpdf->showWatermarkImage = true;
            }

            // Generate PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF (inline display)
            return response($mpdf->Output('event_receipt_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="event_receipt_' . $id . '.pdf"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating event payment receipt PDF', [
                'payment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response('Failed to generate receipt: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/html');
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $post = $request->input('post_value', $request->all());
            $page = max(1, (int) data_get($post, 'page', 1));
            $perPage = max(1, (int) data_get($post, 'per_page', data_get($post, 'length', 10)));
            $start = data_get($post, 'start');
            if ($start !== null) {
                $page = ((int) $start / $perPage) + 1;
            }

            $baseQuery = DB::table('event_payment as ep');

            $search = trim((string) (data_get($post, 'search.value') ?? data_get($post, 'search', '')));
            if ($search !== '') {
                $baseQuery->where(function ($query) use ($search) {
                    $query->where('ep.payer_name', 'like', "%{$search}%")
                        ->orWhere('ep.receiver_name', 'like', "%{$search}%")
                        ->orWhere('ep.event_description', 'like', "%{$search}%")
                        ->orWhere('ep.control_num', 'like', "%{$search}%")
                        ->orWhere('ep.psp_receipt_num', 'like', "%{$search}%")
                        ->orWhere('ep.phone_number', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();
            $recordsTotal = DB::table('event_payment')->count();

            $rows = (clone $baseQuery)
                ->select([
                    'ep.id',
                    'ep.payer_name',
                    'ep.receiver_name',
                    'ep.event_date',
                    'ep.event_description',
                    'ep.email',
                    'ep.phone_number',
                    'ep.amount',
                    'ep.control_num',
                    'ep.psp_receipt_num',
                    'ep.psp_name',
                    'ep.receipt_number',
                    'ep.bill_status',
                    'ep.is_cancelled',
                    'ep.bill_gen_at',
                    'ep.bill_exp_dt',
                    'ep.bill_cancel_date',
                    'ep.cancel_reason',
                    'ep.trx_id',
                    'ep.trx_dt_tm',
                    'ep.paid_amt',
                    'ep.payment_date',
                    'ep.t_status',
                    'ep.error_code',
                    'ep.source',
                    'ep.updated_at',
                    'ep.created_at',
                ])
                ->orderByDesc('ep.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(fn ($row) => $this->formatBillRow($row));

            return $this->sendResponse([
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $recordsFiltered,
                'last_page' => max(1, (int) ceil($recordsFiltered / $perPage)),
                'data' => $rows,
            ], 'Event bills retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve event bills', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to retrieve event bills', [], 0, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payer_name' => 'required|string|max:50',
            'receiver_name' => 'nullable|string|max:50',
            'email' => 'required|email|max:50',
            'phone_number' => 'required|string|max:20',
            'bill_amount' => 'required_without:charge_amount|numeric|min:1',
            'charge_amount' => 'required_without:bill_amount|numeric|min:1',
            'event_date' => 'required|date',
            'event_description' => 'required|string|max:200',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $data = $validator->validated();
        $amount = (float) ($data['bill_amount'] ?? $data['charge_amount']);
        $eventDate = Carbon::parse($data['event_date']);
        $description = trim($data['event_description']);

        try {
            DB::beginTransaction();

            $now = Carbon::now('Africa/Dar_es_Salaam');
            $expiresAt = $now->copy()->addDays(7);
            $phoneDigits = preg_replace('/\D/', '', $data['phone_number']);

            $payment = EventPayment::create([
                'payer_name' => $data['payer_name'],
                'receiver_name' => $data['receiver_name'] ?? null,
                'email' => $data['email'],
                'phone_number' => $phoneDigits ?: $data['phone_number'],
                'event_date' => $eventDate->format('Y-m-d'),
                'event_description' => $description,
                'amount' => $amount,
                'is_cancelled' => '0',
                'source' => 'ECP',
                'created_by' => (string) (Auth::id() ?? ($data['user_id'] ?? 'SYSTEM')),
                'bill_gen_at' => $now->format('Y-m-d H:i:s'),
                'bill_exp_dt' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $billRef = 'ECP' . $payment->id;
            $gepgParams = [
                'payment_ref' => $billRef,
                'amount' => (string) $amount,
                'equiv_amount' => (string) $amount,
                'bill_desc' => $description,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $phoneDigits ?: $billRef,
                'payer_name' => $data['payer_name'],
                'payer_cell' => GePG::normalizePayerCell($data['phone_number']),
                'generated_by' => (string) (Auth::id() ?? ($data['user_id'] ?? 'SYSTEM')),
                'days_expires_after' => 7,
                'payer_email' => $data['email'],
                'bill_gen_date' => Carbon::parse($payment->bill_gen_at)->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($payment->bill_exp_dt)->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);
            if (($gepgResponse['status'] ?? null) !== 'success') {
                $payment->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['message'] ?? ($gepgResponse['error_code'] ?? 'Unknown Error'),
                ]);
                DB::rollBack();

                return $this->sendError('Failed to process event bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

            if (!empty($gepgResponse['control_num'])) {
                $payment->update([
                    'control_num' => $gepgResponse['control_num'],
                    't_status' => 'SP',
                ]);
            }

            $payment->refresh();
            DB::commit();

            return $this->sendResponse([
                'bill_id' => $payment->id,
                'payment_ref' => $billRef,
                'bill_amount' => (float) $payment->amount,
                'control_number' => $payment->control_num ?? 0,
                'gepg_response' => $gepgResponse['data'] ?? null,
            ], 'Control Number Request Successfully Sent');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create event bill', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to create event bill. Please try again or contact support.');
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $paymentId = (int) $request->input('id');

        $payment = EventPayment::query()->find($paymentId);
        if (!$payment) {
            return $this->sendError('Event bill not found');
        }

        if ($payment->trx_id !== null) {
            return $this->sendError('Paid event bills cannot be cancelled');
        }

        if ((string) ($payment->is_cancelled ?? '0') === '1') {
            return $this->sendResponse($this->formatBillRow($payment), 'Event bill is already cancelled');
        }

        $paymentRef = 'ECP' . $payment->id;
        if (!empty($payment->control_num) && $payment->control_num !== '0') {
            $cancelResponse = $this->cancelBillWithGateway($paymentRef);
            if (!$cancelResponse['success']) {
                return $this->sendError($cancelResponse['message']);
            }
        }

        $now = Carbon::now('Africa/Dar_es_Salaam');
        $payment->update([
            'is_cancelled' => '1',
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled from BMS'),
            'bill_cancel_date' => $now->format('Y-m-d H:i:s'),
            'bill_cancel_by' => Auth::id(),
            'psp_receipt_num' => 'CANC' . $now->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);

        return $this->sendResponse($this->formatBillRow($payment->fresh()), 'Event bill cancelled successfully');
    }

    private function cancelBillWithGateway(string $paymentRef): array
    {
        try {
            $response = Http::delete(EnvironmentHelper::GePGBaseUrl() . '/bills/' . $paymentRef);
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway cancellation failed. Status: ' . $response->status(),
                ];
            }

            $payload = $response->json();
            $message = $payload['message'] ?? null;
            if ($message === 7204 || $message === '7204' || $message === 'Successful') {
                return ['success' => true];
            }

            return [
                'success' => false,
                'message' => 'Payment gateway cancellation failed. Code: ' . ($message ?? 'Unknown'),
            ];
        } catch (\Throwable $e) {
            Log::error('Event bill gateway cancellation failed', [
                'payment_ref' => $paymentRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment gateway request failed. Please try again or contact support.',
            ];
        }
    }

    private function formatBillRow(object $bill): array
    {
        $isCancelled = (string) ($bill->is_cancelled ?? '0') === '1';
        $isPaid = !empty($bill->trx_id) || !empty($bill->trx_dt_tm) || !empty($bill->psp_receipt_num);

        return [
            'id' => $bill->id,
            'payer_name' => $bill->payer_name,
            'receiver_name' => $bill->receiver_name ?? null,
            'customer_name' => $bill->payer_name,
            'phone_number' => $bill->phone_number,
            'email' => $bill->email ?? null,
            'event_date' => $bill->event_date ?? null,
            'event_description' => $bill->event_description ?? null,
            'control_number' => $bill->control_num,
            'control_num' => $bill->control_num,
            'contr_num' => $bill->control_num,
            'receipt_number' => $bill->receipt_number,
            'psp_receipt_num' => $bill->psp_receipt_num,
            'psp_name' => $bill->psp_name ?? null,
            'bill_amount' => $bill->amount !== null ? (float) $bill->amount : null,
            'amount' => $bill->amount !== null ? (float) $bill->amount : null,
            'bill_status' => $isCancelled ? 'CANCELLED' : ($isPaid ? 'PAID' : 'PENDING'),
            'is_cancelled' => $isCancelled,
            'bill_generated_at' => $bill->bill_gen_at ?? $bill->created_at ?? null,
            'bill_gen_at' => $bill->bill_gen_at ?? null,
            'bill_expiry_at' => $bill->bill_exp_dt ?? null,
            'bill_cancel_date' => $bill->bill_cancel_date ?? null,
            'cancel_reason' => $bill->cancel_reason ?? null,
            'payment_date' => $bill->payment_date ?? null,
            'paid_amount' => $bill->paid_amt ?? null,
            't_status' => $bill->t_status ?? null,
            'error_code' => $bill->error_code ?? null,
            'source' => $bill->source ?? 'ECP',
            'updated_at' => $bill->updated_at ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_repost' => !$isCancelled && !$isPaid,
            'can_print_receipt' => $isPaid && !empty($bill->psp_receipt_num),
        ];
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
