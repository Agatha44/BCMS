<?php

namespace App\Http\Controllers\Booth;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Account;
use App\Models\AccountBalanceHistory;
use App\Models\BodyType;
use App\Models\PosTerminal;
use App\Models\TollCapture;
use App\Models\TollCaptureHistory;
use App\Models\TollTransaction;
use App\Services\Pos\PosPaymentFlowLogger;
use App\Services\TollCapture\TollCaptureImageStorageService;
use App\Services\TollCapture\TollCaptureStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TollCaptureController extends ConfigurationController
{
    private const POS_TOLL_CAPTURE_PAYMENT_API = 'POST /api/pos/toll-capture/payment';

    private TollCaptureImageStorageService $imageStorage;

    private PosPaymentFlowLogger $posPaymentFlowLogger;

    public function __construct(
        TollCaptureImageStorageService $imageStorage,
        PosPaymentFlowLogger $posPaymentFlowLogger
    ) {
        $this->imageStorage = $imageStorage;
        $this->posPaymentFlowLogger = $posPaymentFlowLogger;
    }

    /**
     * Register vehicle at booth — same image/JSON pattern as POST /api/vehicles/update.
     *
     * Fields: plate_no, lane_number, lane_id, body_type_id, amount, shift_id, user_id,
     * capture_image (multipart File or JSON base64), capture_image_base64, clear_capture_image.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $this->tollCapturePayload($request);

        if ($parseError = $this->jsonBodyParseErrorResponse($request, $payload, ['plate_no'])) {
            return $parseError;
        }

        $maxBase64Chars = (int) config('toll_capture.images.max_base64_chars', 30000000);

        $validator = $this->validateWithCaptureImageExplained($request, $payload, [
            'plate_no' => 'required|string|max:50',
            'lane_id' => 'nullable|integer',
            'lane_number' => 'nullable|string|max:20',
            'body_type_id' => 'nullable|integer|exists:body_type,id',
            'shift_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'amount' => 'nullable|numeric|min:0',
            'capture_image' => $this->captureImageFieldRules($request),
            'capture_image_base64' => 'nullable|string|max:' . $maxBase64Chars,
            'clear_capture_image' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $validated = $validator->validated();

        try {
            $capture = app(TollCaptureStoreService::class)->register(
                $validated,
                $request->file('capture_image'),
                $this->resolvedCaptureImageBase64Payload($validated),
                $request->boolean('clear_capture_image')
            );

            return $this->sendResponse(
                $this->formatCaptureResponse($capture),
                'Toll capture recorded successfully'
            );
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'Unable to determine toll amount') {
                return $this->sendError($e->getMessage(), [
                    'plate_no' => strtoupper(trim((string) ($validated['plate_no'] ?? $payload['plate_no'] ?? ''))),
                    'body_type_id' => $validated['body_type_id'] ?? $payload['body_type_id'] ?? null,
                    'hint' => 'Provide body_type_id or amount, or register the vehicle so price can be resolved.',
                ]);
            }

            return $this->sendError('Invalid capture image: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Toll capture register failed: ' . $e->getMessage(), [
                'request' => $this->requestLogPayload($payload),
            ]);

            return $this->sendError('Failed to record toll capture');
        }
    }

    /**
     * Update capture image only — same pattern as PUT /api/vehicles/image.
     */
    public function updateCaptureImage(Request $request): JsonResponse
    {
        $payload = $this->tollCapturePayload($request);

        if ($parseError = $this->jsonBodyParseErrorResponse($request, $payload, ['toll_capture_id'])) {
            return $parseError;
        }

        $maxBase64Chars = (int) config('toll_capture.images.max_base64_chars', 30000000);

        $validator = $this->validateWithCaptureImageExplained($request, $payload, [
            'toll_capture_id' => 'required|integer|exists:toll_capture,id',
            'capture_image' => $this->captureImageFieldRules($request),
            'capture_image_base64' => 'nullable|string|max:' . $maxBase64Chars,
            'remove' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $validated = $validator->validated();
        $uploadedFile = $request->file('capture_image');
        $hasImage = $this->resolvedCaptureImageBase64Payload($validated) !== null
            || ($uploadedFile instanceof \Illuminate\Http\UploadedFile && $uploadedFile->isValid());
        $remove = filter_var($validated['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $hasImage && ! $remove) {
            return $this->sendError('Either capture_image_base64 (or capture_image) or remove must be provided', [], 400, 422);
        }

        if ($hasImage && $remove) {
            return $this->sendError('Cannot provide both an image and remove', [], 400, 422);
        }

        $capture = TollCapture::pending()->find($validated['toll_capture_id']);
        if (! $capture) {
            return $this->sendError('No pending toll capture found', [
                'toll_capture_id' => $validated['toll_capture_id'],
            ], 404, 404);
        }

        try {
            $capture = app(TollCaptureStoreService::class)->updateImage(
                $capture,
                $request->file('capture_image'),
                $this->resolvedCaptureImageBase64Payload($validated),
                $remove
            );

            return $this->sendResponse(
                $this->formatCaptureResponse($capture),
                $remove ? 'Capture image removed successfully' : 'Capture image updated successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError('Invalid capture image: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Toll capture image update failed: ' . $e->getMessage(), [
                'toll_capture_id' => $validated['toll_capture_id'],
            ]);

            return $this->sendError('Capture image update failed: ' . $e->getMessage());
        }
    }

    /**
     * Serve the stored ANPR capture image for a pending toll capture.
     */
    public function showImage(int $id): BinaryFileResponse|JsonResponse
    {
        $capture = TollCapture::find($id);
        if (! $capture || empty($capture->image)) {
            return $this->sendError('Capture image not found', [], 404, 404);
        }

        $path = $this->imageStorage->absolutePathFromStoredValue($capture->image);
        if ($path === null || ! is_file($path)) {
            return $this->sendError('Capture image file not found', [], 404, 404);
        }

        return response()->file($path);
    }

    /**
     * POS polls for the pending vehicle at its lane.
     */
    public function getPendingForPos(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lane_number' => 'nullable|string|max:20',
            'mac_address' => 'required|string|max:17',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        $laneNumber = trim((string) ($request->lane_number ?? ''));
        $macAddress = strtoupper(trim((string) $request->mac_address));

        $posTerminal = PosTerminal::where('mac_address', $macAddress)->first();

        if (!$posTerminal) {
            return $this->sendError('POS terminal not registered', [
                'mac_address' => $macAddress,
                'hint' => 'Device must register on boot before toll capture can be polled.',
            ]);
        }

        if (!$posTerminal->isLaneConfigured()) {
            return $this->sendError('Lane configuration pending', [
                'mac_address' => $macAddress,
                'terminal_id' => $posTerminal->id,
                'status' => $posTerminal->status,
                'hint' => 'Assign a lane to this device in back-office POS management.',
            ]);
        }

        if ($posTerminal->status !== PosTerminal::STATUS_ACTIVE) {
            return $this->sendError('POS terminal is not active', [
                'terminal_name' => $posTerminal->name,
                'status' => $posTerminal->status,
            ]);
        }

        $laneNumber = $posTerminal->lane_number ?: $laneNumber;

        $posTerminal->updateHeartbeat();

        $laneNumbers = array_values(array_unique(array_filter([
            $posTerminal->lane_number,
            $laneNumber,
        ])));

        $capture = TollCapture::pending()
            ->where(function ($query) use ($posTerminal, $laneNumbers) {
                $matched = false;

                if ($posTerminal->lane_id) {
                    $query->where('lane_id', $posTerminal->lane_id);
                    $matched = true;
                }

                foreach ($laneNumbers as $candidate) {
                    $query->{$matched ? 'orWhere' : 'where'}('lane_number', $candidate);
                    $matched = true;
                }
            })
            ->orderByDesc('created_at')
            ->first();

        if (!$capture) {
            $monitoredLane = $posTerminal->lane_number ?: $laneNumber;

            return $this->sendResponse(null, "No vehicle at booth for lane {$monitoredLane}");
        }

        return $this->sendResponse(
            $this->formatCaptureResponse($capture),
            'Vehicle at booth retrieved successfully'
        );
    }

    /**
     * POS processes card/QR payment for the captured vehicle.
     */
    public function processPayment(Request $request): JsonResponse
    {
        $flowId = $this->posPaymentFlowLogger->start($request, self::POS_TOLL_CAPTURE_PAYMENT_API);

        $validator = Validator::make($request->all(), [
            'toll_capture_id' => 'required|integer|exists:toll_capture,id',
            'card_reference' => 'required_without:account_no|string|max:100',
            'account_no' => 'required_without:card_reference|string|max:30',
            'lane_number' => 'required|string|max:20',
            'mac_address' => 'required|string|max:17',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->logPosPaymentResponse(
                $request,
                $flowId,
                $this->sendError('Validation failed', $validator->errors()->toArray()),
                '2. Validation failed'
            );
        }

        try {
            DB::beginTransaction();

            $capture = TollCapture::where('id', $request->toll_capture_id)
                ->lockForUpdate()
                ->first();

            if (!$capture || $capture->status !== TollCapture::STATUS_PENDING) {
                DB::rollBack();

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('No pending toll capture found', [
                        'toll_capture_id' => $request->toll_capture_id,
                    ]),
                    '3. No pending toll capture'
                );
            }

            $this->posPaymentFlowLogger->step($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, '3. Toll capture loaded', [
                'toll_capture_id' => $capture->id,
                'plate_no' => $capture->plate_no,
                'lane_number' => $capture->lane_number,
                'amount' => $capture->amount,
            ]);

            if ($capture->lane_number !== $request->lane_number) {
                DB::rollBack();

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('Toll capture does not belong to this lane', [
                        'expected_lane' => $capture->lane_number,
                        'requested_lane' => $request->lane_number,
                    ]),
                    '4. Lane mismatch'
                );
            }

            $laneNumber = $request->lane_number;
            $macAddress = strtoupper($request->mac_address);
            $deductionAmount = (float) $capture->amount;

            $posTerminal = PosTerminal::where('mac_address', $macAddress)
                ->where('lane_number', $laneNumber)
                ->first();

            if (!$posTerminal) {
                DB::rollBack();

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('POS terminal not registered', [
                        'mac_address' => $macAddress,
                        'lane_number' => $laneNumber,
                    ]),
                    '5. POS terminal not registered'
                );
            }

            if ($posTerminal->status !== PosTerminal::STATUS_ACTIVE) {
                DB::rollBack();

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('POS terminal is not active'),
                    '6. POS terminal inactive',
                    ['terminal_id' => $posTerminal->id, 'status' => $posTerminal->status]
                );
            }

            $posTerminal->updateHeartbeat();

            $this->posPaymentFlowLogger->step($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, '7. POS terminal validated', [
                'terminal_id' => $posTerminal->id,
                'terminal_name' => $posTerminal->name,
                'mac_address' => $macAddress,
                'lane_number' => $laneNumber,
            ]);

            $account = null;
            $lookupMethod = '';
            $lookupValue = '';

            if ($request->filled('card_reference')) {
                $account = Account::where('nfc_card', $request->card_reference)->first();
                $lookupMethod = 'card_reference';
                $lookupValue = $request->card_reference;
            } elseif ($request->filled('account_no')) {
                $account = Account::where('account_no', $request->account_no)->first();
                $lookupMethod = 'account_no';
                $lookupValue = $request->account_no;
            }

            if (!$account) {
                DB::rollBack();

                $this->posPaymentFlowLogger->logDeductionFailed($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, 'account_not_found', [
                    'lookup_method' => $lookupMethod,
                    'lookup_value' => $lookupValue,
                    'lane_number' => $laneNumber,
                    'deduction_amount' => $deductionAmount,
                    'toll_capture_id' => $capture->id,
                    'plate_no' => $capture->plate_no,
                ]);

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('Account not found', [
                        'lookup_method' => $lookupMethod,
                        'lookup_value' => $lookupValue,
                    ]),
                    '8. Account not found',
                    ['lookup_method' => $lookupMethod, 'lookup_value' => $lookupValue]
                );
            }

            $this->posPaymentFlowLogger->step($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, '9. Account found', [
                'account_no' => $account->account_no,
                'lookup_method' => $lookupMethod,
                'lookup_value' => $lookupValue,
                'current_balance' => (float) $account->account_balance,
            ]);

            if ($account->status != '1') {
                DB::rollBack();

                $this->posPaymentFlowLogger->logDeductionFailed($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, 'account_inactive', [
                    'account_no' => $account->account_no,
                    'account_status' => $account->status,
                    'lane_number' => $laneNumber,
                    'deduction_amount' => $deductionAmount,
                    'toll_capture_id' => $capture->id,
                    'plate_no' => $capture->plate_no,
                ]);

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('Account is not active', [
                        'account_no' => $account->account_no,
                    ]),
                    '10. Account inactive'
                );
            }

            $currentBalance = (float) $account->account_balance;
            if ($currentBalance < $deductionAmount) {
                DB::rollBack();

                $this->posPaymentFlowLogger->logDeductionFailed($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, 'insufficient_balance', [
                    'account_no' => $account->account_no,
                    'customer_name' => $account->full_name,
                    'current_balance' => $currentBalance,
                    'deduction_amount' => $deductionAmount,
                    'shortfall' => $deductionAmount - $currentBalance,
                    'lane_number' => $laneNumber,
                    'payment_method' => $request->filled('account_no') ? 'qr_payment' : 'card_deduction',
                    'toll_capture_id' => $capture->id,
                    'plate_no' => $capture->plate_no,
                ]);

                return $this->logPosPaymentResponse(
                    $request,
                    $flowId,
                    $this->sendError('Insufficient balance', [
                        'account_no' => $account->account_no,
                        'current_balance' => $currentBalance,
                        'deduction_amount' => $deductionAmount,
                        'shortfall' => $deductionAmount - $currentBalance,
                    ]),
                    '11. Insufficient balance'
                );
            }

            $newBalance = $currentBalance - $deductionAmount;
            $account->account_balance = $newBalance;
            $account->updated_at = now();
            $account->save();

            $receiptNumber = TollTransaction::generateReceiptNo();
            $paymentMethod = $request->filled('account_no') ? 'qr_payment' : 'card_deduction';
            $refPrefix = $request->filled('account_no') ? 'QR' : 'CARD';
            $referenceNumber = $request->reference_number ?? $refPrefix . '_' . time() . '_' . $laneNumber;
            $description = $request->description ?? 'Toll Capture Payment - ' . $capture->plate_no . ' - Lane ' . $laneNumber;

            $tollTransaction = new TollTransaction();
            $tollTransaction->receipt_num = $receiptNumber;
            $tollTransaction->vehicle_id = $capture->vehicle_id;
            $tollTransaction->lane_id = $capture->lane_id ?? $laneNumber;
            $tollTransaction->shift_id = $capture->shift_id;
            $tollTransaction->exemption = 0;
            $tollTransaction->plate_no = $capture->plate_no;
            $tollTransaction->account_no = $account->account_no;
            $tollTransaction->charged_amount = $deductionAmount;
            $tollTransaction->trans_type = 'CASHLESS';
            $tollTransaction->created_at = now()->toDateTimeString();
            $tollTransaction->body_type_id = $capture->body_type_id;
            $tollTransaction->status = 1;
            $tollTransaction->save();

            AccountBalanceHistory::create([
                'account_id' => $account->id,
                'transaction_id' => $tollTransaction->id,
                'card_reference' => $account->card_reference,
                'transaction_type' => AccountBalanceHistory::TYPE_DEDUCTION,
                'previous_balance' => $currentBalance,
                'transaction_amount' => $deductionAmount,
                'new_balance' => $newBalance,
                'lane_number' => $laneNumber,
                'terminal_id' => $posTerminal->id,
                'reference_number' => $referenceNumber,
                'description' => $description,
                'metadata' => [
                    'toll_capture_id' => $capture->id,
                    'plate_no' => $capture->plate_no,
                    'body_type_id' => $capture->body_type_id,
                    'payment_method' => $paymentMethod,
                    'lookup_method' => $lookupMethod,
                    'lookup_value' => $lookupValue,
                    'receipt_number' => $receiptNumber,
                ],
                'processed_by' => null,
            ]);

            $paidAt = now()->toDateTimeString();

            $this->archiveCapture($capture, [
                'account_no' => $account->account_no,
                'payment_method' => $paymentMethod,
                'toll_transaction_id' => $tollTransaction->id,
                'receipt_num' => $receiptNumber,
                'paid_at' => $paidAt,
            ]);

            $capture->delete();

            DB::commit();

            $this->posPaymentFlowLogger->logDeduction($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, [
                'account_no' => $account->account_no,
                'customer_name' => $account->full_name,
                'payment_method' => $paymentMethod,
                'lookup_method' => $lookupMethod,
                'lookup_value' => $lookupValue,
                'deduction_amount' => $deductionAmount,
                'previous_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'lane_number' => $laneNumber,
                'terminal_id' => $posTerminal->id,
                'terminal_name' => $posTerminal->name,
                'reference_number' => $referenceNumber,
                'receipt_number' => $receiptNumber,
                'toll_transaction_id' => $tollTransaction->id,
                'toll_capture_id' => $capture->id,
                'plate_no' => $capture->plate_no,
                'description' => $description,
            ]);

            $this->posPaymentFlowLogger->step($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, '12. Payment committed', [
                'plate_no' => $capture->plate_no,
                'account_no' => $account->account_no,
                'amount' => $deductionAmount,
                'receipt_num' => $receiptNumber,
                'toll_transaction_id' => $tollTransaction->id,
            ]);

            return $this->logPosPaymentResponse(
                $request,
                $flowId,
                $this->sendResponse([
                    'plate_no' => $capture->plate_no,
                    'amount' => $deductionAmount,
                    'account_no' => $account->account_no,
                    'previous_balance' => $currentBalance,
                    'new_balance' => $newBalance,
                    'receipt_num' => $receiptNumber,
                    'reference_number' => $referenceNumber,
                    'toll_transaction_id' => $tollTransaction->id,
                ], 'Payment processed successfully'),
                '13. Payment successful'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            $this->posPaymentFlowLogger->exception($flowId, self::POS_TOLL_CAPTURE_PAYMENT_API, $request, $e);

            return $this->logPosPaymentResponse(
                $request,
                $flowId,
                $this->sendError('Payment processing failed'),
                'Exception during payment'
            );
        }
    }

    private function logPosPaymentResponse(
        Request $request,
        string $flowId,
        JsonResponse $response,
        string $step,
        array $context = []
    ): JsonResponse {
        return $this->posPaymentFlowLogger->respond(
            $request,
            self::POS_TOLL_CAPTURE_PAYMENT_API,
            $flowId,
            $response,
            $step,
            $context
        );
    }

    /**
     * Booth operator cancels a pending capture (wrong plate, vehicle left, etc.).
     */
    public function cancel(Request $request): JsonResponse
    {
        $payload = $this->tollCapturePayload($request);

        $validator = Validator::make($payload, [
            'toll_capture_id' => 'required_without:lane_number|integer|exists:toll_capture,id',
            'lane_number' => 'required_without:toll_capture_id|string|max:20',
            'lane_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        $query = TollCapture::pending();

        if (filled($payload['toll_capture_id'] ?? null)) {
            $query->where('id', $payload['toll_capture_id']);
        } else {
            $query->where('lane_number', $payload['lane_number']);
            if (filled($payload['lane_id'] ?? null)) {
                $query->where('lane_id', $payload['lane_id']);
            }
        }

        $capture = $query->orderByDesc('created_at')->first();

        if (!$capture) {
            return $this->sendError('No pending toll capture found');
        }

        DB::beginTransaction();
        try {
            $archived = $this->archiveCapture($capture, [
                'payment_method' => 'cancelled',
                'notes' => $payload['reason'] ?? null,
                'user_id' => $payload['user_id'] ?? $capture->user_id,
                'paid_at' => null,
            ]);

            $capture->delete();

            DB::commit();

            Log::info('Toll capture cancelled', [
                'toll_capture_id' => $capture->id,
                'plate_no' => $capture->plate_no,
                'lane_number' => $capture->lane_number,
                'reason' => $payload['reason'] ?? null,
            ]);

            return $this->sendResponse([
                'archived_id' => $archived->id,
                'plate_no' => $capture->plate_no,
                'lane_number' => $capture->lane_number,
            ], 'Toll capture cancelled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel toll capture', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return $this->sendError('Failed to cancel toll capture');
        }
    }

    /**
     * List archived toll captures (paid or cancelled).
     */
    public function history(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'nullable|string|max:50',
            'lane_number' => 'nullable|string|max:20',
            'payment_method' => 'nullable|string|max:30',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);

        $query = TollCaptureHistory::query()->orderByDesc('created_at');

        if ($request->filled('plate_no')) {
            $query->where('plate_no', strtoupper(trim($request->plate_no)));
        }

        if ($request->filled('lane_number')) {
            $query->where('lane_number', $request->lane_number);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'status_code' => 1,
            'message' => 'Toll capture history retrieved successfully',
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ]);
    }

    private function archiveCapture(TollCapture $capture, array $extra = []): TollCaptureHistory
    {
        $now = now()->toDateTimeString();

        return TollCaptureHistory::create(array_merge([
            'toll_capture_id' => $capture->id,
            'plate_no' => $capture->plate_no,
            'lane_id' => $capture->lane_id,
            'lane_number' => $capture->lane_number,
            'body_type_id' => $capture->body_type_id,
            'vehicle_id' => $capture->vehicle_id,
            'amount' => $capture->amount,
            'image' => $capture->image,
            'shift_id' => $capture->shift_id,
            'user_id' => $capture->user_id,
            'captured_at' => $capture->created_at,
            'created_at' => $now,
        ], $extra));
    }

    private function formatCaptureResponse(TollCapture $capture): array
    {
        $bodyType = $capture->body_type_id
            ? BodyType::with('price')->find($capture->body_type_id)
            : null;

        return [
            'id' => $capture->id,
            'plate_no' => $capture->plate_no,
            'lane_id' => $capture->lane_id,
            'lane_number' => $capture->lane_number,
            'body_type_id' => $capture->body_type_id,
            'body_type' => $bodyType ? [
                'id' => $bodyType->id,
                'name' => $bodyType->name,
                'description' => $bodyType->description,
            ] : null,
            'amount' => (float) $capture->amount,
            'image' => $capture->image,
            'image_url' => $capture->image
                ? url('/api/toll-capture/' . $capture->id . '/image')
                : null,
            'status' => $capture->status,
            'vehicle_id' => $capture->vehicle_id,
            'shift_id' => $capture->shift_id,
            'created_at' => $capture->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestLogPayload(array $payload): array
    {
        $log = $payload;
        foreach (['capture_image', 'capture_image_base64', 'image', 'image_base64', 'vehicle_image_base64'] as $field) {
            if (! isset($log[$field]) || ! is_string($log[$field]) || $log[$field] === '') {
                continue;
            }
            $log[$field] = '[base64 omitted, ' . strlen($log[$field]) . ' chars]';
        }

        return $log;
    }
}
