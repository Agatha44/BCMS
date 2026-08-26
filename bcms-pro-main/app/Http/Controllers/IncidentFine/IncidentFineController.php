<?php

namespace App\Http\Controllers\IncidentFine;

use App\Helpers\EnvironmentHelper;
use App\Http\Controllers\BasicController;
use App\Models\IncidentFine;
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

class IncidentFineController extends BasicController
{
    /**
     * Get all incident fines with pagination
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
            $perPage = max(1, min(100, (int)$perPage)); // Between 1 and 100
            $page = max(1, (int)$page);

            // Start building query
            $query = IncidentFine::query();

            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('driver_name', 'like', "%{$search}%")
                      ->orWhere('plate_number', 'like', "%{$search}%")
                      ->orWhere('payer_name', 'like', "%{$search}%")
                      ->orWhere('control_num', 'like', "%{$search}%")
                      ->orWhere('psp_receipt_num', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%");
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
            $allowedSortFields = ['created_at', 'incident_date', 'amount', 'driver_name', 'plate_number', 'payer_name'];
            if (in_array($sortBy, $allowedSortFields)) {
                $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Get paginated results
            $incidents = $query->paginate($perPage, ['*'], 'page', $page);

            return $this->sendResponse([
                'data' => $incidents->items(),
                'pagination' => [
                    'current_page' => $incidents->currentPage(),
                    'per_page' => $incidents->perPage(),
                    'total' => $incidents->total(),
                    'last_page' => $incidents->lastPage(),
                    'from' => $incidents->firstItem(),
                    'to' => $incidents->lastItem(),
                    'has_more' => $incidents->hasMorePages(),
                ]
            ], 'Incident fines retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching incident fines', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve incident fines', [], 0, 500);
        }
    }

    /**
     * Get incident fine details by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function queryDetails($id): JsonResponse
    {
        try {
            $incident = IncidentFine::find($id);
            
            if (!$incident) {
                return $this->sendError('Incident fine not found', [], 0, 404);
            }
            
            return $this->sendResponse([$incident], 'Incident fine details retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching incident fine details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve incident fine details', [], 0, 500);
        }
    }

    /**
     * Create new incident fine
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'driver_name' => 'required|string|max:50',
                'plate_number' => 'required|string|max:50',
                'vehicle_owner' => 'required|string|max:50',
                'incident_date' => 'required|date',
                'incident_nature' => 'required|integer|in:1,2,3',
                'amount' => 'required|numeric|min:1',
                'phone_number' => 'required|string|max:50',
                'police_rb' => 'required|string|max:50',
                'payment_type' => 'required|integer|in:1,2,3',
                'payer_name' => 'required|string|max:50',
                'email' => 'required|email|max:50'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $user = Auth::user();
            
            // Map incident nature to descriptive text
            $incident_nature_map = [
                1 => 'Vehicle Payment Evasion',
                2 => 'Motorcycle Evasion',
                3 => 'Infrastructure Damage'
            ];

            // Create incident fine record
            $incident = IncidentFine::create([
                'driver_name' => $request->driver_name,
                'plate_number' => $request->plate_number,
                'vehicle_owner' => $request->vehicle_owner,
                'incident_date' => $request->incident_date,
                'nature_incident' => $incident_nature_map[$request->incident_nature],
                'amount' => $request->amount,
                'phone_number' => $request->phone_number,
                'police_rb' => $request->police_rb,
                'payment_type' => $request->payment_type,
                'payer_name' => $request->payer_name,
                'email' => $request->email,
                'created_by' => $user->id ?? null,
                'is_cancelled' => '0',
                'bill_gen_at' => Carbon::now()->tz('Africa/Dar_es_Salaam'),
                'bill_exp_dt' => Carbon::now()->tz('Africa/Dar_es_Salaam')->addDays(30),
                'collection_office' => '1',
                'receipt_type' => '2',
                'payment_method' => '3'
            ]);

            // Post bill to GePG
            $bill_id = "INF" . $incident->id;
            $bill_gen_at = Carbon::now()->tz('Africa/Dar_es_Salaam')->format("Y-m-d\TH:i:s");
            $bill_exp_dt = Carbon::now()->tz('Africa/Dar_es_Salaam')->addDays(30)->format("Y-m-d\TH:i:s");

            $gepgParams = [
                'payment_ref' => $bill_id,
                'amount' => (string)$request->amount,
                'equiv_amount' => (string)$request->amount,
                'bill_desc' => $incident_nature_map[$request->incident_nature] . ' - ' . $request->plate_number,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $request->phone_number,
                'payer_name' => $request->payer_name,
                'payer_cell' => GePG::normalizePayerCell((string) $request->phone_number),
                'generated_by' => (string)($user->id ?? 'system'),
                'days_expires_after' => 30,
                'payer_email' => $request->email,
                'bill_gen_date' => $bill_gen_at,
                'bill_exp_date' => $bill_exp_dt
            ];

            $gepgResponse = GePG::postBill($gepgParams);

            // Update incident with GePG response
            if ($gepgResponse['status'] == 'success') {
                // Control number is not returned in the immediate response; it arrives later via GePG callback.
                $controlNum = $gepgResponse['control_num'] ?? ($gepgResponse['data']['control_num'] ?? null);

                $updateData = ['t_status' => 'SP'];
                if (!empty($controlNum)) {
                    $updateData['control_num'] = $controlNum;
                }
                $incident->update($updateData);

                Log::info('Incident fine created and posted to GePG successfully', [
                    'incident_id' => $incident->id,
                    'control_num' => $controlNum
                ]);

                return $this->sendResponse($incident->fresh(), 'Incident fine created successfully');
            } else {
                // Bill creation failed, update status
                $incident->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['error_code'] ?? null
                ]);

                Log::warning('Incident fine created but GePG posting failed', [
                    'incident_id' => $incident->id,
                    'gepg_response' => $gepgResponse
                ]);

                return $this->sendError('Bill created but failed to post to GePG', $gepgResponse, 0, 200);
            }

        } catch (\Exception $e) {
            Log::error('Error creating incident fine', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to create incident fine', [], 0, 500);
        }
    }

    /**
     * Update incident fine
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function editIncident(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:incident_fine,id',
                'driver_name' => 'required|string|max:50',
                'plate_number' => 'required|string|max:50',
                'vehicle_owner' => 'required|string|max:50',
                'incident_date' => 'required|date',
                'incident_nature' => 'required|integer|in:1,2,3',
                'amount' => 'required|numeric|min:1',
                'phone_number' => 'required|string|max:50',
                'police_rb' => 'required|string|max:50',
                'payment_type' => 'required|integer|in:1,2,3',
                'payer_name' => 'required|string|max:50',
                'email' => 'required|email|max:50'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $incident = IncidentFine::find($request->id);
            
            if (!$incident) {
                return $this->sendError('Incident fine not found', [], 0, 404);
            }

            // Only allow editing if not paid and not cancelled
            if ($incident->isPaid()) {
                return $this->sendError('Cannot edit paid incident fine', [], 0, 400);
            }

            if ($incident->isCancelled()) {
                return $this->sendError('Cannot edit cancelled incident fine', [], 0, 400);
            }

            $user = Auth::user();

            // Map incident nature to descriptive text
            $incident_nature_map = [
                1 => 'Vehicle Payment Evasion',
                2 => 'Motorcycle Evasion',
                3 => 'Infrastructure Damage'
            ];

            $incident->update([
                'driver_name' => $request->driver_name,
                'plate_number' => $request->plate_number,
                'vehicle_owner' => $request->vehicle_owner,
                'incident_date' => $request->incident_date,
                'nature_incident' => $incident_nature_map[$request->incident_nature],
                'amount' => $request->amount,
                'phone_number' => $request->phone_number,
                'police_rb' => $request->police_rb,
                'payment_type' => $request->payment_type,
                'payer_name' => $request->payer_name,
                'email' => $request->email,
                'updated_by' => $user->id ?? null
            ]);

            Log::info('Incident fine updated successfully', [
                'incident_id' => $incident->id,
                'updated_by' => $user->id ?? null
            ]);

            return $this->sendResponse($incident, 'Incident fine updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating incident fine', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to update incident fine', [], 0, 500);
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
                'id' => 'required|integer|exists:incident_fine,id',
                'cancel_reason' => 'required|string|max:200'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $incident = IncidentFine::find($request->id);
            
            if (!$incident) {
                return $this->sendError('Incident fine not found', [], 0, 404);
            }

            // Check if already paid
            if ($incident->isPaid()) {
                return $this->sendError('Cannot cancel paid bill', [], 0, 400);
            }

            // Check if already cancelled
            if ($incident->isCancelled()) {
                return $this->sendError('Bill already cancelled', [], 0, 400);
            }

            $user = Auth::user();

            $incident->update([
                'is_cancelled' => '1',
                'cancel_reason' => $request->cancel_reason,
                'bill_cancel_date' => Carbon::now()->tz('Africa/Dar_es_Salaam'),
                'bill_cancel_by' => $user->id ?? null
            ]);

            Log::info('Bill cancelled successfully', [
                'incident_id' => $incident->id,
                'cancelled_by' => $user->id ?? null,
                'reason' => $request->cancel_reason
            ]);

            return $this->sendResponse($incident, 'Bill cancelled successfully');

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
            $incident = IncidentFine::find($id);
            
            if (!$incident) {
                return $this->sendError('Incident fine not found', [], 0, 404);
            }

            if (!$incident->error_code || $incident->error_code == '7101') {
                return $this->sendResponse([], 'No error codes found');
            }

            // Parse error codes (semicolon separated)
            $error_codes = str_replace(';', ',', $incident->error_code);
            
            // Get error descriptions from gepg_errors table
            $errors = DB::table('gepg_errors')
                ->whereRaw("error_code IN ($error_codes)")
                ->select('error_code', 'description')
                ->get();

            return $this->sendResponse($errors, 'Error details retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error retrieving error details', [
                'incident_id' => $id,
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
                'id' => 'required|integer|exists:incident_fine,id'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error', $validator->errors()->toArray(), 0, 422);
            }

            $incident = IncidentFine::find($request->id);
            
            if (!$incident) {
                return $this->sendError('Incident fine not found', [], 0, 404);
            }

            // Check if already paid
            if ($incident->isPaid()) {
                return $this->sendError('Cannot repost paid bill', [], 0, 400);
            }

            // Check if cancelled
            if ($incident->isCancelled()) {
                return $this->sendError('Cannot repost cancelled bill', [], 0, 400);
            }

            $user = Auth::user();

            // Repost to GePG
            $bill_id = "INF" . $incident->id;
            $bill_gen_at = Carbon::now()->tz('Africa/Dar_es_Salaam')->format("Y-m-d\TH:i:s");
            $bill_exp_dt = Carbon::now()->tz('Africa/Dar_es_Salaam')->addDays(30)->format("Y-m-d\TH:i:s");

            $gepgParams = [
                'payment_ref' => $bill_id,
                'amount' => (string)$incident->amount,
                'equiv_amount' => (string)$incident->amount,
                'bill_desc' => $incident->nature_incident . ' - ' . $incident->plate_number,
                'currency' => 'TZS',
                'payment_type' => 1,
                'payerid' => $incident->phone_number,
                'payer_name' => $incident->payer_name,
                'payer_cell' => $incident->phone_number,
                'generated_by' => (string)($user->id ?? 'system'),
                'days_expires_after' => 30,
                'payer_email' => $incident->email,
                'bill_gen_date' => $bill_gen_at,
                'bill_exp_date' => $bill_exp_dt
            ];

            $gepgResponse = GePG::postBill($gepgParams);

            // Update incident with GePG response
            if ($gepgResponse['status'] == 'success') {
                $incident->update([
                    'control_num' => $gepgResponse['control_num'],
                    't_status' => 'SP',
                    'error_code' => null,
                    'updated_by' => $user->id ?? null
                ]);

                Log::info('Bill reposted to GePG successfully', [
                    'incident_id' => $incident->id,
                    'control_num' => $gepgResponse['control_num']
                ]);

                return $this->sendResponse($incident, 'Bill reposted successfully');
            } else {
                // Repost failed
                $incident->update([
                    't_status' => 'GF',
                    'error_code' => $gepgResponse['error_code'] ?? null,
                    'updated_by' => $user->id ?? null
                ]);

                Log::warning('Bill repost to GePG failed', [
                    'incident_id' => $incident->id,
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
     * Print incident fine receipt as PDF
     *
     * @param int $id
     * @return Response
     */
    public function printReceipt($id): Response
    {
        try {
            $incident = IncidentFine::find($id);
            
            if (!$incident) {
                return response('Incident fine not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            // Check if incident has been paid
            if (empty($incident->psp_receipt_num)) {
                return response('Receipt not available. Incident fine has not been paid.', 400)
                    ->header('Content-Type', 'text/html');
            }

            // Prepare receipt data
            $amount = $incident->paid_amt ?? $incident->amount;
            $receipt_data = [
                'receipt_number' => $incident->receipt_number ?? $incident->control_num ?? 'N/A',
                'payment_date' => $incident->payment_date ?? $incident->created_at,
                'psp_receipt_num' => $incident->psp_receipt_num,
                'amount' => $amount,
                'amount_word' => $this->numberToWords($amount) . ' Shillings Only',
                'trx_dt_tm' => $incident->trx_dt_tm ?? $incident->payment_date ?? $incident->created_at,
                'driver_name' => $incident->driver_name,
                'plate_number' => $incident->plate_number,
                'payer_name' => $incident->payer_name,
                'nature_incident' => $incident->nature_incident,
                'control_num' => $incident->control_num,
                'pay_ref_id' => $incident->pay_ref_id,
            ];

            // Get logo and convert to base64 for mPDF
            $logoPath = public_path('images/nssf-log1.png');
            $logoBase64 = '';
            
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
            }
            
            // Generate HTML from blade template
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
                // Use SetWatermarkImage - alpha between 0 and 1 (0.05 = 5% opacity)
                $mpdf->SetWatermarkImage($logoPath, 0.05);
                $mpdf->showWatermarkImage = true;
            }

            // Generate PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF (inline display)
            return response($mpdf->Output('incident_receipt_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="incident_receipt_' . $id . '.pdf"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating incident receipt PDF', [
                'incident_id' => $id,
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

    /**
     * Print incident fine bill/order form as PDF
     *
     * @param int $id
     * @return Response
     */
    public function printBill($id): Response
    {
        try {
            $incident = IncidentFine::find($id);
            
            if (!$incident) {
                return response('Incident fine not found', 404)
                    ->header('Content-Type', 'text/html');
            }

            // Check if incident has control number
            if (empty($incident->control_num)) {
                return response('Bill not available. Control number is missing.', 400)
                    ->header('Content-Type', 'text/html');
            }

            // Get user info
            $user = Auth::user();

            // Prepare bill data
            $bill_details = [
                'payer_name' => $incident->payer_name ?? '-',
                'phone_number' => $incident->phone_number ?? '-',
                'control_num' => $incident->control_num,
                'amount' => $incident->amount,
                'bill_exp_dt' => $incident->bill_exp_dt ? Carbon::parse($incident->bill_exp_dt)->format('d-M-Y') : '-',
            ];

            // Account name (NSSF account name)
            $accountName = 'NATIONAL SOCIAL SECURITY FUND';
            
            // Payment description
            $payment_for = $incident->nature_incident . ' - ' . $incident->plate_number;

            // Get logo and convert to base64 for mPDF
            $logoPath1 = public_path('images/nembo.png');
            $logoPath2 = public_path('images/nssf-log1.png');
            $logoBase641 = '';
            $logoBase642 = '';
            
            if (file_exists($logoPath1)) {
                $logoData = file_get_contents($logoPath1);
                $logoBase641 = 'data:image/png;base64,' . base64_encode($logoData);
            }
            
            if (file_exists($logoPath2)) {
                $logoData = file_get_contents($logoPath2);
                $logoBase642 = 'data:image/png;base64,' . base64_encode($logoData);
            }

            // Generate HTML from blade template
            $html = View::make('receipts.incident_bill', compact(
                'bill_details',
                'accountName',
                'payment_for',
                'user',
                'logoBase641',
                'logoBase642'
            ))->render();

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

            // Generate PDF
            $mpdf->WriteHTML($html);
            
            // Output PDF (inline display)
            return response($mpdf->Output('incident_bill_' . $id . '.pdf', 'I'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="incident_bill_' . $id . '.pdf"'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating incident bill PDF', [
                'incident_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response('Failed to generate bill: ' . $e->getMessage(), 500)
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

            $baseQuery = DB::table('incident_fine as inf');

            $search = trim((string) (data_get($post, 'search.value') ?? data_get($post, 'search', '')));
            if ($search !== '') {
                $baseQuery->where(function ($query) use ($search) {
                    $query->where('inf.driver_name', 'like', "%{$search}%")
                        ->orWhere('inf.plate_number', 'like', "%{$search}%")
                        ->orWhere('inf.payer_name', 'like', "%{$search}%")
                        ->orWhere('inf.nature_incident', 'like', "%{$search}%")
                        ->orWhere('inf.control_num', 'like', "%{$search}%")
                        ->orWhere('inf.psp_receipt_num', 'like', "%{$search}%")
                        ->orWhere('inf.phone_number', 'like', "%{$search}%");
                });
            }

            $recordsFiltered = (clone $baseQuery)->count();
            $recordsTotal = DB::table('incident_fine')->count();

            $rows = (clone $baseQuery)
                ->select([
                    'inf.id',
                    'inf.driver_name',
                    'inf.plate_number',
                    'inf.vehicle_owner',
                    'inf.nature_incident',
                    'inf.incident_date',
                    'inf.payer_name',
                    'inf.phone_number',
                    'inf.email',
                    'inf.amount',
                    'inf.control_num',
                    'inf.psp_receipt_num',
                    'inf.psp_name',
                    'inf.receipt_number',
                    'inf.pay_ref_id',
                    'inf.bill_status',
                    'inf.is_cancelled',
                    'inf.bill_gen_at',
                    'inf.bill_exp_dt',
                    'inf.bill_cancel_date',
                    'inf.cancel_reason',
                    'inf.trx_id',
                    'inf.trx_dt_tm',
                    'inf.paid_amt',
                    'inf.payment_date',
                    'inf.t_status',
                    'inf.error_code',
                    'inf.source',
                    'inf.updated_at',
                    'inf.created_at',
                ])
                ->orderByDesc('inf.id')
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
            ], 'Incident bills retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve incident bills', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('Failed to retrieve incident bills', [], 0, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $response = $this->create($request);
        $payload = $response->getData(true);

        if (!($payload['success'] ?? false)) {
            return $response;
        }

        $incident = $payload['data'] ?? [];
        if ($incident instanceof IncidentFine) {
            $incident = $incident->toArray();
        }

        return $this->sendResponse([
            'bill_id' => $incident['id'] ?? null,
            'payment_ref' => isset($incident['id']) ? 'INF' . $incident['id'] : null,
            'bill_amount' => isset($incident['amount']) ? (float) $incident['amount'] : null,
            'control_number' => $incident['control_num'] ?? 0,
        ], $payload['message'] ?? 'Incident fine created successfully');
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

        $incident = IncidentFine::query()->find((int) $request->input('id'));
        if (!$incident) {
            return $this->sendError('Incident bill not found');
        }

        if ($incident->isPaid()) {
            return $this->sendError('Paid incident bills cannot be cancelled');
        }

        if ($incident->isCancelled()) {
            return $this->sendResponse($this->formatBillRow($incident), 'Incident bill is already cancelled');
        }

        $paymentRef = 'INF' . $incident->id;
        if (!empty($incident->control_num) && $incident->control_num !== '0') {
            $cancelResponse = $this->cancelBillWithGateway($paymentRef);
            if (!$cancelResponse['success']) {
                return $this->sendError($cancelResponse['message']);
            }
        }

        $now = Carbon::now('Africa/Dar_es_Salaam');
        $incident->update([
            'is_cancelled' => '1',
            'cancel_reason' => $request->input('cancel_reason', 'Cancelled from BMS'),
            'bill_cancel_date' => $now,
            'bill_cancel_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        return $this->sendResponse($this->formatBillRow($incident->fresh()), 'Incident bill cancelled successfully');
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
            Log::error('Incident bill gateway cancellation failed', [
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
            'customer_name' => $bill->payer_name,
            'driver_name' => $bill->driver_name ?? null,
            'plate_number' => $bill->plate_number ?? null,
            'vehicle_owner' => $bill->vehicle_owner ?? null,
            'nature_incident' => $bill->nature_incident ?? null,
            'incident_date' => $bill->incident_date ?? null,
            'control_number' => $bill->control_num,
            'control_num' => $bill->control_num,
            'contr_num' => $bill->control_num,
            'receipt_number' => $bill->receipt_number,
            'psp_receipt_num' => $bill->psp_receipt_num,
            'pay_ref_id' => $bill->pay_ref_id ?? null,
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
            'source' => $bill->source ?? 'INF',
            'updated_at' => $bill->updated_at ?? null,
            'can_cancel' => !$isCancelled && !$isPaid,
            'can_print_receipt' => $isPaid && !empty($bill->psp_receipt_num),
            'can_repost' => ($bill->t_status ?? null) === 'GF' && !$isCancelled && !$isPaid,
        ];
    }
}

