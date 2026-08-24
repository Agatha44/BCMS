<?php

namespace App\Http\Controllers\Advertisement;

use App\Helpers\EnvironmentHelper;
use App\Helpers\GePG;
use App\Http\Controllers\BasicController;
use App\Models\BridgeBill;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;

class AdvertisementController extends BasicController
{
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

            $baseQuery = DB::table('bridge_bills as bb')
                ->where('bb.bill_source', 2);

            $search = trim((string) (data_get($post, 'search.value') ?? data_get($post, 'search', '')));
            if ($search !== '') {
                $baseQuery->where(function ($query) use ($search) {
                    $query->where('bb.payer_name', 'like', "%{$search}%")
                        ->orWhere('bb.bill_desc', 'like', "%{$search}%")
                        ->orWhere('bb.dist_param', 'like', "%{$search}%")
                        ->orWhere('bb.contr_num', 'like', "%{$search}%")
                        ->orWhere('bb.psp_receipt_num', 'like', "%{$search}%")
                        ->orWhere('bb.phone_number', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();
            $recordsTotal = DB::table('bridge_bills')->where('bill_source', 2)->count();

            $rows = (clone $baseQuery)
                ->select([
                    'bb.id',
                    'bb.contr_num',
                    'bb.psp_receipt_num',
                    'bb.receipt_number',
                    'bb.bill_amount',
                    'bb.bill_desc',
                    'bb.bill_status',
                    'bb.bill_gen_at',
                    'bb.bill_exp_dt',
                    'bb.bill_cancel_date',
                    'bb.cancel_reason',
                    'bb.is_cancelled',
                    'bb.trx_id',
                    'bb.trx_dt_tm',
                    'bb.paid_amt',
                    'bb.payment_date',
                    'bb.payer_name',
                    'bb.phone_number',
                    'bb.dist_param',
                    'bb.source',
                    'bb.t_status',
                    'bb.error_code',
                    'bb.updated_at',
                ])
                ->orderByDesc('bb.id')
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
            ], 'Advertisement bills retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve advertisement bills', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to retrieve advertisement bills', [], 0, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:20',
            'bill_amount' => 'required|numeric|min:1',
            'advertisement_type' => 'required|string|max:150',
            'advert_start_date' => 'required|date',
            'advert_end_date' => 'required|date|after_or_equal:advert_start_date',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $data = $validator->validated();
        $startDate = Carbon::parse($data['advert_start_date']);
        $endDate = Carbon::parse($data['advert_end_date']);
        $billDescription = sprintf(
            'Advertisement Charges for %s covering the period from %s to %s',
            $data['payer_name'],
            $startDate->format('F j, Y'),
            $endDate->format('F j, Y')
        );

        try {
            DB::beginTransaction();

            $now = Carbon::now('Africa/Dar_es_Salaam');
            $expiresAt = $now->copy()->addDays(31);
            $phoneDigits = preg_replace('/\D/', '', $data['phone_number']);
            $phoneForStorage = $phoneDigits ? (int) ltrim(substr($phoneDigits, -10), '0') : null;

            $bill = BridgeBill::create([
                'bill_amount' => $data['bill_amount'],
                'bill_desc' => $billDescription,
                'phone_number' => $phoneForStorage,
                'bill_gen_by' => Auth::id() ?? ($data['user_id'] ?? null),
                'bill_source' => 2,
                'bill_status' => BridgeBill::REQUESTED,
                'source' => 'ADV',
                'dist_param' => $data['advertisement_type'],
                'payer_name' => $data['payer_name'],
                'bill_gen_at' => $now->format('Y-m-d H:i:s'),
                'bill_exp_dt' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $billRef = 'ADV' . $bill->id;
            $gepgParams = [
                'payment_ref' => $billRef,
                'amount' => (string) $data['bill_amount'],
                'equiv_amount' => (string) $data['bill_amount'],
                'bill_desc' => $billDescription,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $phoneDigits ?: $billRef,
                'payer_name' => $data['payer_name'],
                'payer_cell' => GePG::normalizePayerCell($data['phone_number']),
                'generated_by' => (string) (Auth::id() ?? ($data['user_id'] ?? 'SYSTEM')),
                'days_expires_after' => 31,
                'payer_email' => $data['email'],
                'bill_gen_date' => Carbon::parse($bill->bill_gen_at)->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($bill->bill_exp_dt)->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);
            if (($gepgResponse['status'] ?? null) !== 'success') {
                $bill->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['message'] ?? 'Unknown Error',
                ]);
                DB::rollBack();

                return $this->sendError('Failed to process advertisement bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

            $bill->refresh();
            DB::commit();

            return $this->sendResponse([
                'bill_id' => $bill->id,
                'payment_ref' => $billRef,
                'bill_amount' => (float) $bill->bill_amount,
                'control_number' => $bill->contr_num ?? 0,
                'gepg_response' => $gepgResponse['data'] ?? null,
            ], 'Control Number Request Successfully Sent');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create advertisement bill', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to create advertisement bill. Please try again or contact support.');
        }
    }

    public function orderForm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $billId = (int) $request->input('id');

        $bill = DB::table('bridge_bills')
            ->where('id', $billId)
            ->where('bill_source', 2)
            ->first();

        if (!$bill) {
            return $this->sendError('Advertisement bill not found');
        }

        return $this->sendResponse($this->formatBillRow($bill), 'Advertisement order form retrieved successfully');
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

        $billId = (int) $request->input('id');

        $bill = BridgeBill::query()
            ->where('id', $billId)
            ->where('bill_source', 2)
            ->first();

        if (!$bill) {
            return $this->sendError('Advertisement bill not found');
        }

        if ($bill->trx_id !== null || $bill->trx_dt_tm !== null) {
            return $this->sendError('Paid advertisement bills cannot be cancelled');
        }

        if ((int) ($bill->is_cancelled ?? 0) === 1 || (string) $bill->bill_status === BridgeBill::CANCELLED) {
            return $this->sendResponse($this->formatBillRow($bill), 'Advertisement bill is already cancelled');
        }

        $paymentRef = 'ADV' . $bill->id;
        if (!empty($bill->contr_num) && $bill->contr_num !== '0') {
            $cancelResponse = $this->cancelWithGateway($paymentRef);
            if (!$cancelResponse['success']) {
                return $this->sendError($cancelResponse['message']);
            }
        }

        $bill->update([
            'is_cancelled' => 1,
            'bill_status' => BridgeBill::CANCELLED,
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled from BMS'),
            'bill_cancel_date' => Carbon::now('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s'),
            'bill_cancel_by' => Auth::id(),
            'psp_receipt_num' => 'CANC' . Carbon::now('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);

        return $this->sendResponse($this->formatBillRow($bill->fresh()), 'Advertisement bill cancelled successfully');
    }

    public function printReceipt($id): Response
    {
        try {
            $bill = DB::table('bridge_bills')
                ->where('id', (int) $id)
                ->where('bill_source', 2)
                ->first();

            if (!$bill) {
                return response('Advertisement bill not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            if (empty($bill->psp_receipt_num) && empty($bill->trx_dt_tm)) {
                return response('Receipt not available. Payment has not been completed.', 400)
                    ->header('Content-Type', 'text/html');
            }

            $amount = $bill->paid_amt ?? $bill->bill_amount;
            $payerName = $bill->payer_name ?: $this->extractPayerName($bill->bill_desc ?? null);
            $receipt_data = [
                'receipt_number' => $bill->receipt_number ?? $bill->contr_num ?? 'N/A',
                'payment_date' => $bill->payment_date ?? $bill->trx_dt_tm ?? $bill->receipt_date,
                'psp_receipt_num' => $bill->psp_receipt_num,
                'amount' => $amount,
                'amount_word' => $this->numberToWords((int) $amount) . ' Shillings Only',
                'trx_dt_tm' => $bill->trx_dt_tm ?? $bill->payment_date,
                'payer_name' => $payerName,
                'phone_number' => $bill->phone_number,
                'bill_desc' => $bill->bill_desc,
                'advertisement_type' => $bill->dist_param,
                'contr_num' => $bill->contr_num,
                'pay_ref_id' => $bill->pay_ref_id,
            ];

            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }

            $html = View::make('receipts.advertisement_receipt', compact('receipt_data', 'logoBase64'))->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 10,
                'margin_footer' => 10,
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
            ]);

            if (file_exists($logoPath)) {
                $mpdf->SetWatermarkImage($logoPath, 0.05);
                $mpdf->showWatermarkImage = true;
            }

            $mpdf->WriteHTML($html);

            return response($mpdf->Output('advertisement_receipt_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="advertisement_receipt_' . $id . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error generating advertisement receipt PDF', [
                'bill_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response('Failed to generate receipt: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/html');
        }
    }

    private function cancelWithGateway(string $paymentRef): array
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
            Log::error('Advertisement bill gateway cancellation failed', [
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
        $status = (string) ($bill->bill_status ?? '');
        $isCancelled = (int) ($bill->is_cancelled ?? 0) === 1 || $status === BridgeBill::CANCELLED;
        $isPaid = !empty($bill->trx_id) || !empty($bill->trx_dt_tm) || strtoupper($status) === 'PAID';

        return [
            'id' => $bill->id,
            'payer_name' => $bill->payer_name ?: $this->extractPayerName($bill->bill_desc ?? null),
            'customer_name' => $bill->payer_name ?: $this->extractPayerName($bill->bill_desc ?? null),
            'phone_number' => $bill->phone_number,
            'advertisement_type' => $bill->dist_param,
            'source' => $bill->source ?? 'ADV',
            'control_number' => $bill->contr_num,
            'contr_num' => $bill->contr_num,
            'receipt_number' => $bill->receipt_number,
            'psp_receipt_num' => $bill->psp_receipt_num,
            'bill_amount' => $bill->bill_amount !== null ? (float) $bill->bill_amount : null,
            'bill_desc' => $bill->bill_desc,
            'bill_status' => $isCancelled ? 'CANCELLED' : ($isPaid ? 'PAID' : ($status === BridgeBill::REQUESTED ? 'PENDING' : $status)),
            'is_cancelled' => $isCancelled,
            'bill_generated_at' => $bill->bill_gen_at,
            'bill_expiry_at' => $bill->bill_exp_dt,
            'bill_cancel_date' => $bill->bill_cancel_date ?? null,
            'cancel_reason' => $bill->cancel_reason ?? null,
            'payment_date' => $bill->payment_date ?? null,
            'paid_amount' => $bill->paid_amt ?? null,
            't_status' => $bill->t_status ?? null,
            'error_code' => $bill->error_code ?? null,
            'updated_at' => $bill->updated_at ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_print' => $isPaid && !$isCancelled,
        ];
    }

    private function numberToWords($number)
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

        if ($number == 0) {
            return 'Zero';
        }
        if ($number < 10) {
            return $ones[$number];
        }
        if ($number < 20) {
            return $teens[$number - 10];
        }
        if ($number < 100) {
            $tensPart = $tens[floor($number / 10)];
            $onesPart = $ones[$number % 10];
            return $tensPart . ($onesPart ? ' ' . $onesPart : '');
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

    private function extractPayerName(?string $description): ?string
    {
        if (!$description) {
            return null;
        }

        if (preg_match('/for\s+(.*?)\s+covering/i', $description, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
