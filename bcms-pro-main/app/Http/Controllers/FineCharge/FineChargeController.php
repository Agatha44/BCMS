<?php

namespace App\Http\Controllers\FineCharge;

use App\Helpers\EnvironmentHelper;
use App\Helpers\GePG;
use App\Http\Controllers\BasicController;
use App\Models\FineCharge;
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

class FineChargeController extends BasicController
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

            $baseQuery = DB::table('fine_charge as fc');

            $search = trim((string) (data_get($post, 'search.value') ?? data_get($post, 'search', '')));
            if ($search !== '') {
                $baseQuery->where(function ($query) use ($search) {
                    $query->where('fc.payer_name', 'like', "%{$search}%")
                        ->orWhere('fc.plate_number', 'like', "%{$search}%")
                        ->orWhere('fc.driver_name', 'like', "%{$search}%")
                        ->orWhere('fc.charge_description', 'like', "%{$search}%")
                        ->orWhere('fc.control_num', 'like', "%{$search}%")
                        ->orWhere('fc.psp_receipt_num', 'like', "%{$search}%")
                        ->orWhere('fc.phone_number', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();
            $recordsTotal = DB::table('fine_charge')->count();

            $rows = (clone $baseQuery)
                ->select([
                    'fc.id',
                    'fc.payer_name',
                    'fc.plate_number',
                    'fc.driver_name',
                    'fc.charge_date',
                    'fc.charge_description',
                    'fc.email',
                    'fc.phone_number',
                    'fc.amount',
                    'fc.control_num',
                    'fc.psp_receipt_num',
                    'fc.psp_name',
                    'fc.receipt_number',
                    'fc.bill_status',
                    'fc.is_cancelled',
                    'fc.bill_gen_at',
                    'fc.bill_exp_dt',
                    'fc.bill_cancel_date',
                    'fc.cancel_reason',
                    'fc.trx_id',
                    'fc.trx_dt_tm',
                    'fc.paid_amt',
                    'fc.payment_date',
                    'fc.t_status',
                    'fc.error_code',
                    'fc.updated_at',
                    'fc.created_at',
                ])
                ->orderByDesc('fc.id')
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
            ], 'Fine charge bills retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve fine charge bills', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to retrieve fine charge bills', [], 0, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payer_name' => 'required|string|max:50',
            'plate_number' => 'required|string|max:50',
            'driver_name' => 'nullable|string|max:50',
            'email' => 'required|email|max:50',
            'phone_number' => 'required|string|max:20',
            'bill_amount' => 'required_without:charge_amount|numeric|min:1',
            'charge_amount' => 'required_without:bill_amount|numeric|min:1',
            'charge_date' => 'required|date',
            'charge_description' => 'required|string|max:200',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $data = $validator->validated();
        $amount = (float) ($data['bill_amount'] ?? $data['charge_amount']);
        $chargeDate = Carbon::parse($data['charge_date']);
        $description = trim($data['charge_description']);

        try {
            DB::beginTransaction();

            $now = Carbon::now('Africa/Dar_es_Salaam');
            $expiresAt = $now->copy()->addDays(30);
            $phoneDigits = preg_replace('/\D/', '', $data['phone_number']);

            $charge = FineCharge::create([
                'payer_name' => $data['payer_name'],
                'plate_number' => strtoupper(trim($data['plate_number'])),
                'driver_name' => $data['driver_name'] ?? null,
                'email' => $data['email'],
                'phone_number' => $phoneDigits ?: $data['phone_number'],
                'charge_date' => $chargeDate->format('Y-m-d'),
                'charge_description' => $description,
                'amount' => $amount,
                'is_cancelled' => '0',
                'source' => 'FCB',
                'created_by' => (string) (Auth::id() ?? ($data['user_id'] ?? 'SYSTEM')),
                'bill_gen_at' => $now->format('Y-m-d H:i:s'),
                'bill_exp_dt' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $billRef = 'FCB' . $charge->id;
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
                'days_expires_after' => 30,
                'payer_email' => $data['email'],
                'bill_gen_date' => Carbon::parse($charge->bill_gen_at)->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($charge->bill_exp_dt)->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);
            if (($gepgResponse['status'] ?? null) !== 'success') {
                $charge->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['message'] ?? ($gepgResponse['error_code'] ?? 'Unknown Error'),
                ]);
                DB::rollBack();

                return $this->sendError('Failed to process fine charge bill: ' . ($gepgResponse['message'] ?? 'Unknown Error'));
            }

            if (!empty($gepgResponse['control_num'])) {
                $charge->update([
                    'control_num' => $gepgResponse['control_num'],
                    't_status' => 'SP',
                ]);
            }

            $charge->refresh();
            DB::commit();

            return $this->sendResponse([
                'bill_id' => $charge->id,
                'payment_ref' => $billRef,
                'bill_amount' => (float) $charge->amount,
                'control_number' => $charge->control_num ?? 0,
                'gepg_response' => $gepgResponse['data'] ?? null,
            ], 'Control Number Request Successfully Sent');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create fine charge bill', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to create fine charge bill. Please try again or contact support.');
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

        $charge = FineCharge::query()->find((int) $request->input('id'));
        if (!$charge) {
            return $this->sendError('Fine charge bill not found');
        }

        if ($charge->trx_id !== null) {
            return $this->sendError('Paid fine charge bills cannot be cancelled');
        }

        if ((string) ($charge->is_cancelled ?? '0') === '1') {
            return $this->sendResponse($this->formatBillRow($charge), 'Fine charge bill is already cancelled');
        }

        $paymentRef = 'FCB' . $charge->id;
        if (!empty($charge->control_num) && $charge->control_num !== '0') {
            $cancelResponse = $this->cancelBillWithGateway($paymentRef);
            if (!$cancelResponse['success']) {
                return $this->sendError($cancelResponse['message']);
            }
        }

        $now = Carbon::now('Africa/Dar_es_Salaam');
        $charge->update([
            'is_cancelled' => '1',
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled from BMS'),
            'bill_cancel_date' => $now->format('Y-m-d H:i:s'),
            'bill_cancel_by' => Auth::id(),
            'psp_receipt_num' => 'CANC' . $now->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);

        return $this->sendResponse($this->formatBillRow($charge->fresh()), 'Fine charge bill cancelled successfully');
    }

    public function printReceipt($id): Response
    {
        try {
            $charge = FineCharge::find($id);

            if (!$charge) {
                return response('Fine charge bill not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            if (empty($charge->psp_receipt_num)) {
                return response('Receipt not available. Payment has not been completed.', 400)
                    ->header('Content-Type', 'text/html');
            }

            $amount = $charge->paid_amt ?? $charge->amount;
            $receipt_data = [
                'receipt_number' => $charge->receipt_number ?? $charge->control_num ?? 'N/A',
                'payment_date' => $charge->payment_date ?? $charge->created_at,
                'psp_receipt_num' => $charge->psp_receipt_num,
                'amount' => $amount,
                'amount_word' => $this->numberToWords($amount) . ' Shillings Only',
                'trx_dt_tm' => $charge->trx_dt_tm ?? $charge->payment_date ?? $charge->created_at,
                'payer_name' => $charge->payer_name,
                'plate_number' => $charge->plate_number,
                'driver_name' => $charge->driver_name,
                'charge_description' => $charge->charge_description,
                'charge_date' => $charge->charge_date,
                'control_num' => $charge->control_num,
                'pay_ref_id' => $charge->pay_ref_id,
            ];

            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }

            $html = View::make('receipts.fine_charge_receipt', compact('receipt_data', 'logoBase64'))->render();

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

            return response($mpdf->Output('fine_charge_receipt_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="fine_charge_receipt_' . $id . '.pdf"',
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating fine charge receipt PDF', [
                'charge_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response('Failed to generate receipt: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/html');
        }
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
            Log::error('Fine charge bill gateway cancellation failed', [
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
            'plate_number' => $bill->plate_number ?? null,
            'driver_name' => $bill->driver_name ?? null,
            'customer_name' => $bill->payer_name,
            'phone_number' => $bill->phone_number,
            'email' => $bill->email ?? null,
            'charge_date' => $bill->charge_date ?? null,
            'charge_description' => $bill->charge_description ?? null,
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
            'updated_at' => $bill->updated_at ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_print_receipt' => $isPaid && !empty($bill->psp_receipt_num),
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
}
