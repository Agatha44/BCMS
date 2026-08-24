<?php

namespace App\Http\Controllers\TopUp;

use App\Helpers\EnvironmentHelper;
use App\Helpers\GePG;
use App\Http\Controllers\Configurations\ConfigurationController;
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

class TopUpController extends ConfigurationController
{
    public function topUpBills(Request $request): JsonResponse
    {
        // Accept any of the search param names the frontend may send.
        $q = trim((string) ($request->input('q')
            ?? $request->input('search')
            ?? $request->input('search_term')
            ?? data_get($request->input('post_value'), 'search', '')));
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));

        $query = DB::table('top_up as tu')
            ->leftJoin('account as au', 'tu.account_no', '=', 'au.account_no')
            ->select([
                'tu.id',
                'tu.account_no',
                DB::raw("TRIM(CONCAT(COALESCE(au.first_name,''), ' ', COALESCE(au.middle_name,''), ' ', COALESCE(au.surname,''))) as cust_name"),
                'tu.bill_amount',
                'tu.bill_desc',
                'tu.bill_gen_at',
                'tu.bill_exp_dt',
                'tu.contr_num',
                'tu.psp_receipt_num',
                'tu.is_cancelled',
                'tu.trx_dt_tm',
                'tu.t_status',
                'tu.source',
            ])
            ->when($q !== '', fn ($qb) => $this->applyTopUpBillSearch($qb, $q))
            ->orderByDesc('tu.id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($t) {
            $isCancelled = (int) ($t->is_cancelled ?? 0) === 1;
            $isPaid = !is_null($t->trx_dt_tm) || !empty($t->psp_receipt_num);

            if ($isCancelled) {
                $t->bill_status = 'CANCELLED';
            } elseif ($isPaid) {
                $t->bill_status = 'PAID';
            } elseif (!is_null($t->bill_exp_dt) && now()->greaterThan($t->bill_exp_dt)) {
                $t->bill_status = 'EXPIRED';
            } else {
                $t->bill_status = 'UNPAID';
            }

            $t->source = $t->source ?? 'TUP';
            $t->can_cancel = !$isCancelled && !$isPaid;
            $t->can_repost = !$isCancelled && !$isPaid;
            $t->can_print = !empty($t->psp_receipt_num) || !is_null($t->trx_dt_tm);

            return $t;
        });

        return $this->sendResponse([
            'data' => $paginator->items(),
            'summary' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Top-ups fetched successfully');
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

        $bill = DB::table('top_up')->where('id', (int) $request->input('id'))->first();

        if (!$bill) {
            return $this->sendError('Prepayment bill not found', [], 0, 404);
        }

        if (!empty($bill->psp_receipt_num) || !empty($bill->trx_dt_tm)) {
            return $this->sendError('Paid prepayment bills cannot be cancelled', [], 0, 400);
        }

        if ((int) ($bill->is_cancelled ?? 0) === 1) {
            return $this->sendResponse($this->formatTopUpBillRow($bill), 'Prepayment bill is already cancelled');
        }

        $paymentRef = 'TUP' . $bill->id;
        if (!empty($bill->contr_num) && $bill->contr_num !== '0') {
            $cancelResponse = $this->cancelWithGateway($paymentRef);
            if (!$cancelResponse['success']) {
                return $this->sendError($cancelResponse['message']);
            }
        }

        $now = Carbon::now('Africa/Dar_es_Salaam');
        DB::table('top_up')->where('id', $bill->id)->update([
            'is_cancelled' => 1,
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled from BMS'),
            'bill_cancel_date' => $now->format('Y-m-d H:i:s'),
            'bill_cancel_by' => Auth::id(),
            'psp_receipt_num' => 'CANC' . $now->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);

        $bill = DB::table('top_up')->where('id', $bill->id)->first();

        return $this->sendResponse($this->formatTopUpBillRow($bill), 'Prepayment bill cancelled successfully');
    }

    public function repost(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error: ' . $validator->errors()->first(), ['error' => $validator->errors()]);
        }

        $bill = DB::table('top_up')->where('id', (int) $request->input('id'))->first();

        if (!$bill) {
            return $this->sendError('Prepayment bill not found', [], 0, 404);
        }

        if (!empty($bill->psp_receipt_num) || !empty($bill->trx_dt_tm)) {
            return $this->sendError('Cannot repost paid bill', [], 0, 400);
        }

        if ((int) ($bill->is_cancelled ?? 0) === 1) {
            return $this->sendError('Cannot repost cancelled bill', [], 0, 400);
        }

        $account = DB::table('account')->where('account_no', $bill->account_no)->first();
        if (!$account) {
            return $this->sendError('Account not found for this bill');
        }

        try {
            $now = Carbon::now('Africa/Dar_es_Salaam');
            $billGenAt = $now->format('Y-m-d H:i:s');
            $billExpDt = $now->copy()->addDay()->format('Y-m-d H:i:s');

            DB::table('top_up')->where('id', $bill->id)->update([
                'bill_gen_at' => $billGenAt,
                'bill_exp_dt' => $billExpDt,
                'updated_at' => now(),
            ]);

            $billRef = 'TUP' . $bill->id;
            $payerName = trim(($account->first_name ?? '') . ' ' . ($account->middle_name ?? '') . ' ' . ($account->surname ?? ''));

            $gepgParams = [
                'payment_ref' => $billRef,
                'amount' => (string) $bill->bill_amount,
                'equiv_amount' => (string) $bill->bill_amount,
                'bill_desc' => $bill->bill_desc ?? 'Toll Fee',
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => (string) $bill->account_no,
                'payer_name' => $payerName,
                'payer_cell' => GePG::normalizePayerCell((string) ($account->phone ?? '')),
                'generated_by' => (string) $bill->account_no,
                'days_expires_after' => 1,
                'payer_email' => $account->email ?? 'noreply@example.com',
                'bill_gen_date' => Carbon::parse($billGenAt)->format("Y-m-d\TH:i:s"),
                'bill_exp_date' => Carbon::parse($billExpDt)->format("Y-m-d\TH:i:s"),
            ];

            $gepgResponse = GePG::postBill($gepgParams);

            if (in_array($gepgResponse['status'] ?? null, ['invalid_params', 'invalid_request'], true)) {
                return $this->sendError('GePG Error: ' . ($gepgResponse['message'] ?? 'Invalid request'));
            }

            if (($gepgResponse['status'] ?? null) === 'success' || (isset($gepgResponse['data']) && is_array($gepgResponse['data']))) {
                $responseData = $gepgResponse['data'] ?? [];
                $controlNum = is_array($responseData)
                    ? ($responseData['contr_num'] ?? $responseData['control_no'] ?? null)
                    : null;

                $updateData = [
                    't_status' => 'SP',
                    'error_code' => null,
                    'updated_at' => now(),
                ];
                if ($controlNum) {
                    $updateData['contr_num'] = $controlNum;
                }
                DB::table('top_up')->where('id', $bill->id)->update($updateData);

                $bill = DB::table('top_up')->where('id', $bill->id)->first();

                Log::info('Prepayment bill reposted to GePG successfully', [
                    'bill_id' => $bill->id,
                    'control_number' => $bill->contr_num,
                ]);

                return $this->sendResponse([
                    'bill_id' => $bill->id,
                    'bill_amount' => (float) $bill->bill_amount,
                    'control_number' => $bill->contr_num ?? 0,
                    'gepg_response' => $gepgResponse['data'] ?? null,
                ], 'Bill reposted successfully');
            }

            DB::table('top_up')->where('id', $bill->id)->update([
                't_status' => 'GF',
                'error_code' => $gepgResponse['message'] ?? 'Unknown Error',
                'updated_at' => now(),
            ]);

            Log::warning('Prepayment bill repost to GePG failed', [
                'bill_id' => $bill->id,
                'gepg_response' => $gepgResponse,
            ]);

            return $this->sendError('Failed to repost bill to GePG', $gepgResponse, 0, 200);
        } catch (\Throwable $e) {
            Log::error('Failed to repost prepayment bill', [
                'bill_id' => $request->input('id'),
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to repost bill', [], 0, 500);
        }
    }

    public function printReceipt($id): Response
    {
        try {
            $topUp = DB::table('top_up as tu')
                ->leftJoin('account as au', 'tu.account_no', '=', 'au.account_no')
                ->select([
                    'tu.*',
                    DB::raw("TRIM(CONCAT(COALESCE(au.first_name,''), ' ', COALESCE(au.middle_name,''), ' ', COALESCE(au.surname,''))) as customer_name"),
                ])
                ->where('tu.id', (int) $id)
                ->first();

            if (!$topUp) {
                return response('Prepayment bill not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            if (empty($topUp->psp_receipt_num) && empty($topUp->trx_dt_tm)) {
                return response('Receipt not available. Payment has not been completed.', 400)
                    ->header('Content-Type', 'text/html');
            }

            $amount = $topUp->paid_amt ?? $topUp->bill_amount;
            $receipt_data = [
                'receipt_number' => $topUp->receipt_number ?? $topUp->contr_num ?? 'N/A',
                'payment_date' => $topUp->payment_date ?? $topUp->trx_dt_tm ?? $topUp->receipt_date,
                'psp_receipt_num' => $topUp->psp_receipt_num,
                'amount' => $amount,
                'amount_word' => $this->numberToWords((int) $amount) . ' Shillings Only',
                'trx_dt_tm' => $topUp->trx_dt_tm ?? $topUp->payment_date,
                'payer_name' => $topUp->payer_name ?: $topUp->customer_name,
                'account_no' => $topUp->account_no,
                'customer_name' => $topUp->customer_name,
                'bill_desc' => $topUp->bill_desc,
                'contr_num' => $topUp->contr_num,
                'pay_ref_id' => $topUp->pay_ref_id,
            ];

            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }

            $html = View::make('receipts.top_up_receipt', compact('receipt_data', 'logoBase64'))->render();

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

            return response($mpdf->Output('prepayment_receipt_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="prepayment_receipt_' . $id . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error generating prepayment receipt PDF', [
                'top_up_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response('Failed to generate receipt: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/html');
        }
    }

    /**
     * Search prepayment bills by account number, customer name, phone, email, control number, or receipt.
     */
    private function applyTopUpBillSearch($query, string $search): void
    {
        $term = trim($search);
        if ($term === '') {
            return;
        }

        $like = '%' . $term . '%';
        $lowerLike = '%' . mb_strtolower($term) . '%';

        $query->where(function ($q) use ($like, $lowerLike) {
            $q->where('tu.account_no', 'like', $like)
                ->orWhere('tu.psp_receipt_num', 'like', $like)
                ->orWhere('tu.contr_num', 'like', $like)
                ->orWhere('au.first_name', 'like', $like)
                ->orWhere('au.middle_name', 'like', $like)
                ->orWhere('au.surname', 'like', $like)
                ->orWhere('au.phone', 'like', $like)
                ->orWhere('au.email', 'like', $like)
                ->orWhereRaw(
                    "LOWER(REPLACE(CONCAT_WS(' ', COALESCE(au.first_name,''), COALESCE(au.middle_name,''), COALESCE(au.surname,'')), '_', ' ')) LIKE ?",
                    [$lowerLike]
                )
                ->orWhereRaw(
                    "LOWER(REPLACE(CONCAT_WS(' ', COALESCE(au.first_name,''), COALESCE(au.surname,'')), '_', ' ')) LIKE ?",
                    [$lowerLike]
                );
        });
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
            Log::error('Prepayment bill gateway cancellation failed', [
                'payment_ref' => $paymentRef,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment gateway request failed. Please try again or contact support.',
            ];
        }
    }

    /**
     * @param  object  $bill
     * @return array<string, mixed>
     */
    private function formatTopUpBillRow(object $bill): array
    {
        $isCancelled = (int) ($bill->is_cancelled ?? 0) === 1;
        $isPaid = !empty($bill->trx_dt_tm) || !empty($bill->psp_receipt_num);

        if ($isCancelled) {
            $billStatus = 'CANCELLED';
        } elseif ($isPaid) {
            $billStatus = 'PAID';
        } elseif (!is_null($bill->bill_exp_dt) && now()->greaterThan($bill->bill_exp_dt)) {
            $billStatus = 'EXPIRED';
        } else {
            $billStatus = 'UNPAID';
        }

        return [
            'id' => $bill->id,
            'account_no' => $bill->account_no,
            'bill_amount' => (float) $bill->bill_amount,
            'bill_desc' => $bill->bill_desc,
            'bill_exp_dt' => $bill->bill_exp_dt,
            'bill_gen_at' => $bill->bill_gen_at,
            'contr_num' => $bill->contr_num,
            'gepg_control_number' => $bill->contr_num,
            'api_control_number' => $bill->contr_num,
            'pay_ref_id' => $bill->pay_ref_id ?? null,
            'psp_receipt_num' => $bill->psp_receipt_num ?? null,
            'receipt_number' => $bill->receipt_number ?? null,
            'payment_date' => $bill->payment_date ?? null,
            'trx_dt_tm' => $bill->trx_dt_tm ?? null,
            'paid_amt' => isset($bill->paid_amt) ? (float) $bill->paid_amt : null,
            'payer_name' => $bill->payer_name ?? null,
            'tin' => $bill->tin ?? null,
            'source' => $bill->source ?? 'TUP',
            'bill_status' => $billStatus,
            'is_cancelled' => $isCancelled,
            'cancel_reason' => $bill->cancel_reason ?? null,
            'bill_cancel_date' => $bill->bill_cancel_date ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_repost' => !$isCancelled && !$isPaid,
            'can_print' => $isPaid,
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

