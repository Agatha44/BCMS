<?php

namespace App\Http\Controllers\Vehicle;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Account;
use App\Models\Vehicle;
use App\Models\AccountVehicle;
use App\Models\BodyType;
use App\Models\PriceList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Vehicle\VehicleUpdateService;
use App\Services\Vehicle\VehicleImageStorageService;
use App\Services\Vehicle\CardNumberGeneratorService;
use App\Services\Audit\OwenItAuditWriter;


class VehicleController extends ConfigurationController
{
    public function getVehicleData(Request $request): JsonResponse
    {
        // validatore

        $validator = Validator::make($request->all(), [
            'plate_no' => 'required|string',
            'body_type_id' => 'nullable|integer|exists:body_type,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $plate_no = $request->plate_no;

        $result = Vehicle::where('plate_no', $plate_no)
            ->with(['bundles', 'bodyType', 'account'])
            ->first();

        $base64 = Vehicle::getImageBase64($result->id);
        $result->image = $base64;

        if ($result == null) {
            return $this->sendError('Vehicle not found');
        }

        return $this->sendResponse($result, 'Vehicle data retrieved successfully');
    }

    public function getVehicleImage($vehicle_id)
    {
        $image = Vehicle::getImageContent($vehicle_id);
        if ($image == null) {
            return $this->sendError('Image not found');
        }
        return response($image)->header('Content-Type', 'image/png');
    }

    /**
     * Update or remove vehicle image.
     * Accepts base64 image data to upload/update, or remove flag to set image to null.
     */
    public function updateVehicleImage(Request $request): JsonResponse
    {
        $payload = $this->jsonRequestPayload($request);
        if (! array_key_exists('image_base64', $payload) && array_key_exists('vehicle_image_base64', $payload)) {
            $payload['image_base64'] = $payload['vehicle_image_base64'];
        }

        if ($parseError = $this->jsonBodyParseErrorResponse($request, $payload, ['vehicle_id'])) {
            return $parseError;
        }

        $validator = Validator::make($payload, [
            'vehicle_id' => 'required|integer|exists:vehicle,id',
            'image_base64' => 'nullable|string',
            'remove' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()], 400, 422);
        }

        $hasImage = filled($payload['image_base64'] ?? null);
        $remove = filter_var($payload['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$hasImage && !$remove) {
            return $this->sendError('Either image_base64 or remove must be provided', [], 400, 422);
        }

        if ($hasImage && $remove) {
            return $this->sendError('Cannot provide both image_base64 and remove', [], 400, 422);
        }

        $vehicle = Vehicle::find($payload['vehicle_id']);
        if (!$vehicle) {
            return $this->sendError('Vehicle not found', [], 404, 404);
        }

        try {
            DB::beginTransaction();

            if ($remove) {
                // $this->deleteVehicleImageFile($vehicle);
                $vehicle->image = null;
                $vehicle->save();
                DB::commit();
                return $this->sendResponse($vehicle->fresh(), 'Vehicle image removed successfully');
            }

            // Handle base64 upload
            $base64Data = $payload['image_base64'];

            // Strip data URI prefix if present (e.g. data:image/png;base64,...)
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $extension = strtolower($matches[1]);
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            } else {
                $extension = 'png';
            }

            $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                return $this->sendError('Invalid image format. Allowed: ' . implode(', ', $allowedExtensions), [], 400, 422);
            }

            $fileData = base64_decode($base64Data, true);
            if ($fileData === false) {
                return $this->sendError('Invalid base64 image data', [], 400, 422);
            }

            // Validate image data (max ~5MB)
            if (strlen($fileData) > 5 * 1024 * 1024) {
                return $this->sendError('Image size exceeds 5MB limit', [], 400, 422);
            }

            $tmpFile = sys_get_temp_dir() . '/vehicle_img_' . uniqid() . '.' . $extension;
            file_put_contents($tmpFile, $fileData);
            $imageInfo = @getimagesize($tmpFile);
            @unlink($tmpFile);
            if ($imageInfo === false) {
                return $this->sendError('Invalid or corrupted image data', [], 400, 422);
            }

            $basePath = Vehicle::getImageStorageBasePath();
            if (!is_dir($basePath)) {
                if (!mkdir($basePath, 0755, true)) {
                    return $this->sendError('Image storage directory is not writable', [], 500, 500);
                }
            }

            $filename = 'vehicle_' . $vehicle->id . '_' . time() . '.' . $extension;
            $fullPath = $basePath . '/' . $filename;

            if (file_put_contents($fullPath, $fileData) === false) {
                DB::rollBack();
                return $this->sendError('Failed to save image', [], 500, 500);
            }

            $this->deleteVehicleImageFile($vehicle);

            $vehicle->image = ':' . $filename;
            $vehicle->save();

            DB::commit();
            return $this->sendResponse($vehicle->fresh(), 'Vehicle image updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle image update failed: ' . $e->getMessage());
            return $this->sendError('Vehicle image update failed: ' . $e->getMessage(), [], 500, 500);
        }
    }


    public function associateVehicle(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_no' => 'required|string|exists:vehicle,plate_no',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', ['errors' => $validator->errors()], 400, 422);
            }

            $user = Auth::user();
            if (!$user) {
                return $this->sendError('User not authenticated', [], 401, 401);
            }

            $account = Account::where('id', $user->id)->first();
            if ($account == null) {
                return $this->sendError('Account not found', [], 404, 404);
            }

            $vehicle = Vehicle::where('plate_no', $request->plate_no)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', ['plate_no' => $request->plate_no], 404, 404);
            }

            // Check if vehicle is already associated with this account
            $existingAccountVehicle = AccountVehicle::where([
                'vehicle_id' => $vehicle->id,
                'status' => AccountVehicle::STATUS_ACTIVE,
                'account_id' => $account->account_no
            ])->first();

            if ($existingAccountVehicle) {
                return $this->sendError('Vehicle already associated with this account', [
                    'plate_no' => $vehicle->plate_no,
                    'account_id' => $account->account_no
                ], 409, 409);
            }

            // Check if vehicle is associated with another account
            $otherAccountVehicle = AccountVehicle::where([
                'vehicle_id' => $vehicle->id,
                'status' => AccountVehicle::STATUS_ACTIVE
            ])->where('account_id', '!=', $account->account_no)->first();

            if ($otherAccountVehicle) {
                return $this->sendError('Vehicle is already associated with another account', [
                    'plate_no' => $vehicle->plate_no,
                    'current_account_id' => $otherAccountVehicle->account_id
                ], 409, 409);
            }

            // Use database transaction for data consistency
            DB::beginTransaction();
            try {
                $priorAv = AccountVehicle::where([
                    'account_id' => $account->account_no,
                    'vehicle_id' => $vehicle->id,
                ])->first();

                $accountVehicle = AccountVehicle::updateOrCreate([
                    'account_id' => $account->account_no,
                    'vehicle_id' => $vehicle->id,
                ], [
                    'status' => AccountVehicle::STATUS_ACTIVE
                ]);

                DB::commit();

                $writer = app(OwenItAuditWriter::class);
                $freshAv = $accountVehicle->fresh()->toArray();
                if ($priorAv === null) {
                    $writer->record('created', AccountVehicle::class, (int) $accountVehicle->id, null, $freshAv, 'associateVehicle');
                } else {
                    $writer->record('updated', AccountVehicle::class, (int) $accountVehicle->id, $priorAv->toArray(), $freshAv, 'associateVehicle');
                }

                return $this->sendResponse($accountVehicle, 'Vehicle associated with account successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->sendError('Failed to associate vehicle', [
                    'error' => $e->getMessage(),
                    'plate_no' => $vehicle->plate_no
                ], 500, 500);
            }

        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sendError('Database error occurred', [
                'error' => $e->getMessage(),
                'plate_no' => $request->plate_no ?? 'unknown'
            ], 500, 500);
        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred', [
                'error' => $e->getMessage(),
                'plate_no' => $request->plate_no ?? 'unknown'
            ], 500, 500);
        }
    }

    public function disassociateVehicle(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_no' => 'required|string|exists:vehicle,plate_no',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', ['errors' => $validator->errors()], 400, 422);
            }

            $user = Auth::user();
            if (!$user) {
                return $this->sendError('User not authenticated', [], 401, 401);
            }

            $account = Account::where('id', $user->id)->first();
            if ($account == null) {
                return $this->sendError('Account not found', [], 404, 404);
            }

            $vehicle = Vehicle::where('plate_no', $request->plate_no)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', ['plate_no' => $request->plate_no], 404, 404);
            }

            $accountVehicle = AccountVehicle::where([
                'vehicle_id' => $vehicle->id,
                'status' => AccountVehicle::STATUS_ACTIVE,
                'account_id' => $account->account_no
            ])->first();

            if (is_null($accountVehicle)) {
                return $this->sendError('Vehicle not associated with this account', [
                    'plate_no' => $vehicle->plate_no,
                    'account_id' => $account->account_no
                ], 404, 404);
            }

            // Use database transaction for data consistency
            DB::beginTransaction();
            try {
                $removed = $accountVehicle->toArray();
                $removedId = (int) $removed['id'];
                $accountVehicle->delete();

                DB::commit();

                app(OwenItAuditWriter::class)->record('deleted', AccountVehicle::class, $removedId, $removed, null, 'disassociateVehicle');

                return $this->sendResponse($removed, 'Vehicle disassociated from account successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->sendError('Failed to disassociate vehicle', [
                    'error' => $e->getMessage(),
                    'plate_no' => $vehicle->plate_no
                ], 500, 500);
            }

        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sendError('Database error occurred', [
                'error' => $e->getMessage(),
                'plate_no' => $request->plate_no ?? 'unknown'
            ], 500, 500);
        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred', [
                'error' => $e->getMessage(),
                'plate_no' => $request->plate_no ?? 'unknown'
            ], 500, 500);
        }
    }

        public function actionEnrollVehicleAssociate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_num' => 'required|string|exists:vehicle,plate_no',
                'rfid_tag_no' => 'nullable|string',
                'account_no' => 'nullable|string',
                'user_id' => 'nullable|integer',
                'card_number' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', ['errors' => $validator->errors()], 400, 422);
            }

            $vehicle = Vehicle::where('plate_no', $request->plate_num)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', ['plate_num' => $request->plate_num], 404, 404);
            }

            // Use database transaction for data consistency
            DB::beginTransaction();
            try {
                $vehicleAuditKeys = $this->vehicleAuditAttributeKeys();
                $vehicleBefore = $vehicle->only($vehicleAuditKeys);
                $priorAv = null;
                if (!empty($request->account_no)) {
                    $priorAv = AccountVehicle::where([
                        'account_id' => $request->account_no,
                        'vehicle_id' => $vehicle->id,
                    ])->first();
                }

                // Use provided card number, otherwise auto-generate when vehicle has none
                if (!empty($request->card_number)) {
                    $vehicle->rfid_tag_status = 0;
                    $vehicle->card_number = $request->card_number;
                    $vehicle->card_number_status = 1;
                } elseif (empty($vehicle->card_number)) {
                    $cardNumber = app(CardNumberGeneratorService::class)->generate();
                    if (!$cardNumber) {
                        DB::rollBack();
                        return $this->sendError('Failed to generate unique card number', [
                            'plate_num' => $vehicle->plate_no
                        ], 500, 500);
                    }
                    $vehicle->rfid_tag_status = null;
                    $vehicle->card_number = $cardNumber;
                    $vehicle->card_number_status = 1;
                }

                // Generate RFID tag number if not provided
                if (empty($request->rfid_tag_no)) {
                    $vehicle->rfid_tag_no = 'E' . strtoupper(\Illuminate\Support\Str::random(23));
                } else {
                    $vehicle->rfid_tag_no = $request->rfid_tag_no;
                }

                $vehicle->account_no = $request->account_no;
                $vehicle->updated_by = $request->user_id;

                if (!$vehicle->save()) {
                    throw new \Exception('Failed to save vehicle data');
                }

                $accountVehicle = null;

                // Check for existing account-vehicle association if account_no is provided
                if (!empty($request->account_no)) {
                    $existingActive = AccountVehicle::where([
                        'vehicle_id' => $vehicle->id,
                        'status' => AccountVehicle::STATUS_ACTIVE,
                    ])->first();

                    if ($existingActive && (string) $existingActive->account_id !== (string) $request->account_no) {
                        DB::rollBack();
                        return $this->sendError('Vehicle already associated with an account', [
                            'existing_account_id' => $existingActive->account_id,
                            'plate_num' => $vehicle->plate_no
                        ], 409, 409);
                    }

                    // Upsert: new row after disassociate delete, or upgrade legacy inactive row if any remain
                    $accountVehicle = AccountVehicle::updateOrCreate(
                        [
                            'account_id' => $request->account_no,
                            'vehicle_id' => $vehicle->id,
                        ],
                        [
                            'status' => AccountVehicle::STATUS_ACTIVE,
                        ]
                    );

                    if (!$accountVehicle) {
                        throw new \Exception('Failed to create account-vehicle association');
                    }
                }

                DB::commit();

                $writer = app(OwenItAuditWriter::class);
                $vehicleAfter = $vehicle->fresh()->only($vehicleAuditKeys);
                if (json_encode($vehicleBefore) !== json_encode($vehicleAfter)) {
                    $writer->record('updated', Vehicle::class, (int) $vehicle->id, $vehicleBefore, $vehicleAfter, 'actionEnrollVehicleAssociate');
                }
                if (!empty($request->account_no) && $accountVehicle !== null) {
                    $freshAv = $accountVehicle->fresh()->toArray();
                    if ($priorAv === null) {
                        $writer->record('created', AccountVehicle::class, (int) $accountVehicle->id, null, $freshAv, 'actionEnrollVehicleAssociate');
                    } else {
                        $writer->record('updated', AccountVehicle::class, (int) $accountVehicle->id, $priorAv->toArray(), $freshAv, 'actionEnrollVehicleAssociate');
                    }
                }

                return $this->sendResponse([
                    'tag_no' => $vehicle->rfid_tag_no,
                    'card_number' => $vehicle->card_number,
                    'card_number_status' => $vehicle->card_number_status,
                    'account_no' => $vehicle->account_no,
                    'plate_no' => $vehicle->plate_no,
                    'account_vehicle_association' => $accountVehicle
                ], 'Vehicle enrolled successfully');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->sendError('Enrollment failed', [
                    'error' => $e->getMessage(),
                    'plate_num' => $vehicle->plate_no
                ], 500, 500);
            }

        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sendError('Database error occurred', [
                'error' => $e->getMessage(),
                'plate_num' => $request->plate_num ?? 'unknown'
            ], 500, 500);
        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred', [
                'error' => $e->getMessage(),
                'plate_num' => $request->plate_num ?? 'unknown'
            ], 500, 500);
        }
    }

    public function actionDisassociateVehicle(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_num' => 'required|string|exists:vehicle,plate_no',
                'account_no' => 'nullable|string',
                'user_id' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', ['errors' => $validator->errors()], 400, 422);
            }

            $vehicle = Vehicle::where('plate_no', $request->plate_num)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', ['plate_num' => $request->plate_num], 404, 404);
            }

            // Find existing account-vehicle association (if any)
            $existingAssociation = AccountVehicle::where([
                'vehicle_id' => $vehicle->id,
                'status' => AccountVehicle::STATUS_ACTIVE
            ])->first();

            // If account_no is provided and association exists, verify it matches
            if (!empty($request->account_no) && $existingAssociation && $existingAssociation->account_id !== $request->account_no) {
                return $this->sendError('Vehicle is not associated with the specified account', [
                    'current_account_id' => $existingAssociation->account_id,
                    'requested_account_id' => $request->account_no,
                    'plate_num' => $vehicle->plate_no
                ], 409, 409);
            }

            // Use database transaction for data consistency
            DB::beginTransaction();
            try {
                $vehicleAuditKeys = $this->vehicleAuditAttributeKeys();
                $vehicleBefore = $vehicle->only($vehicleAuditKeys);
                $removedAssociation = $existingAssociation ? $existingAssociation->toArray() : null;

                // Update vehicle fields
                $vehicle->account_no = null;
                $vehicle->updated_by = $request->user_id;

                if (!$vehicle->save()) {
                    throw new \Exception('Failed to update vehicle data');
                }

                if ($existingAssociation) {
                    $existingAssociation->delete();
                }

                DB::commit();

                $writer = app(OwenItAuditWriter::class);
                $vehicleAfter = $vehicle->fresh()->only($vehicleAuditKeys);
                if (json_encode($vehicleBefore) !== json_encode($vehicleAfter)) {
                    $writer->record('updated', Vehicle::class, (int) $vehicle->id, $vehicleBefore, $vehicleAfter, 'actionDisassociateVehicle');
                }
                if ($removedAssociation !== null) {
                    $writer->record('deleted', AccountVehicle::class, (int) $removedAssociation['id'], $removedAssociation, null, 'actionDisassociateVehicle');
                }

                $responseData = [
                    'plate_no' => $vehicle->plate_no,
                    'disassociated_account_id' => $removedAssociation !== null ? ($removedAssociation['account_id'] ?? null) : null,
                ];

                if ($removedAssociation !== null) {
                    $responseData['account_vehicle_association'] = $removedAssociation;
                }

                return $this->sendResponse($responseData, 'Vehicle disassociated successfully');

            } catch (\Exception $e) {
                DB::rollBack();
                return $this->sendError('Disassociation failed', [
                    'error' => $e->getMessage(),
                    'plate_num' => $vehicle->plate_no
                ], 500, 500);
            }

        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sendError('Database error occurred', [
                'error' => $e->getMessage(),
                'plate_num' => $request->plate_num ?? 'unknown'
            ], 500, 500);
        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred', [
                'error' => $e->getMessage(),
                'plate_num' => $request->plate_num ?? 'unknown'
            ], 500, 500);
        }
    }

    /**
     * @return list<string>
     */
    private function vehicleAuditAttributeKeys(): array
    {
        return ['plate_no', 'rfid_tag_no', 'body_type_id', 'card_number', 'card_number_status', 'rfid_tag_status', 'image', 'account_no', 'updated_by'];
    }

    public function searchUnassociatedVehicles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $searchTerm = trim($request->get('search', ''));

        // Get vehicles that are not associated with any account
        $query = Vehicle::whereDoesntHave('accountVehicles', function($query) {
            $query->where('status', AccountVehicle::STATUS_ACTIVE);
        })->with(['bodyType']);

        // Apply search filter if provided
        if (!empty($searchTerm)) {
            $query->where('plate_no', 'LIKE', '%' . $searchTerm . '%');
        }

        // Limit results to prevent overwhelming the UI
        $vehicles = $query->limit(20)->get();

        // For debugging - let's also check if the vehicle exists at all
        if (!empty($searchTerm)) {
            $allVehicles = Vehicle::where('plate_no', 'LIKE', '%' . $searchTerm . '%')->get();
            $debugInfo = [
                'search_term' => $searchTerm,
                'total_matching_vehicles' => $allVehicles->count(),
                'unassociated_vehicles' => $vehicles->count(),
                'all_matching_plates' => $allVehicles->pluck('plate_no')->toArray()
            ];
        } else {
            $debugInfo = [
                'search_term' => $searchTerm,
                'total_unassociated_vehicles' => $vehicles->count()
            ];
        }

        // Transform data to match Flutter TypeAhead format
        $formattedVehicles = $vehicles->map(function($vehicle) {
            return [
                'id' => 'v' . $vehicle->id,
                'plate_number' => $vehicle->plate_no,
                'body_type' => $vehicle->bodyType ? $vehicle->bodyType->name : 'Unknown',
                'image_url' => $vehicle->image ? $this->getVehicleImageUrl($vehicle->id) : null,
            ];
        });

        return $this->sendResponse([
            'vehicles' => $formattedVehicles,
            'debug_info' => $debugInfo
        ], 'Unassociated vehicles retrieved successfully');
    }

    public function searchAllVehicles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $searchTerm = trim($request->get('search', ''));

        // Search all vehicles (for debugging)
        $query = Vehicle::with(['bodyType', 'accountVehicles']);

        // Apply search filter if provided
        if (!empty($searchTerm)) {
            $query->where('plate_no', 'LIKE', '%' . $searchTerm . '%');
        }

        // Limit results
        $vehicles = $query->limit(20)->get();

        // Transform data to match Flutter TypeAhead format
        $formattedVehicles = $vehicles->map(function($vehicle) {
            $isAssociated = $vehicle->accountVehicles->where('status', AccountVehicle::STATUS_ACTIVE)->count() > 0;

            return [
                'id' => 'v' . $vehicle->id,
                'plate_number' => $vehicle->plate_no,
                'body_type' => $vehicle->bodyType ? $vehicle->bodyType->name : 'Unknown',
                'image_url' => $vehicle->image ? $this->getVehicleImageUrl($vehicle->id) : null,
                'is_associated' => $isAssociated,
            ];
        });

        return $this->sendResponse([
            'vehicles' => $formattedVehicles,
            'search_term' => $searchTerm,
            'total_found' => $vehicles->count()
        ], 'All vehicles retrieved successfully');
    }

    private function getVehicleImageUrl($vehicleId): ?string
    {
        // Check if vehicle has an image
        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle || !$vehicle->image) {
            return null;
        }

        // Return the image URL - you may need to adjust this based on your image storage setup
        return url('/api/vehicle/image/' . $vehicleId);
    }

    /**
     * Create a new vehicle (Sanctum). Optional account link and optional image (multipart `vehicle_image` or JSON base64 / `vehicle_image_base64`).
     */
    public function createVehicle(Request $request): JsonResponse
    {
        $rules = [
            'plate_no' => 'required|string|unique:vehicle,plate_no',
            'account_no' => 'nullable|string|exists:account,account_no',
            'body_type_id' => 'required|integer|exists:body_type,id',
            'card_number' => 'nullable|string',
            'rfid_tag_no' => 'nullable|string',
            'created_by' => 'nullable|integer',
            'vehicle_image' => $this->vehicleImageFieldRules($request),
            'vehicle_image_base64' => 'nullable|string|max:' . (int) config('vehicle.images.max_base64_chars', 30000000),
            'clear_vehicle_image' => 'nullable|boolean',
        ];

        $validator = $this->validateWithVehicleImageExplained($request, $request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        $validated = $validator->validated();
        $createdBy = $validated['created_by'] ?? Auth::id();

        DB::beginTransaction();

        try {
            $vehicle = new Vehicle();
            $vehicle->plate_no = $validated['plate_no'];
            $vehicle->account_no = $validated['account_no'] ?? null;
            $vehicle->body_type_id = $validated['body_type_id'];
            $vehicle->created_by = $createdBy;

            if (!empty($validated['card_number'])) {
                $vehicle->rfid_tag_status = 0;
                $vehicle->card_number = $validated['card_number'];
                $vehicle->card_number_status = 1;
            }

            if (!empty($validated['rfid_tag_no'])) {
                $vehicle->rfid_tag_no = $validated['rfid_tag_no'];
            }

            if (!$vehicle->save()) {
                DB::rollBack();

                return $this->sendError('Failed to create vehicle');
            }

            $vehicle->refresh();

            $imageStorage = app(VehicleImageStorageService::class);
            $file = $request->file('vehicle_image');
            $base64Payload = $this->resolvedVehicleImageBase64Payload($validated);

            if ($file !== null && $file->isValid()) {
                $vehicle->image = $imageStorage->storeUploadedFile((int) $vehicle->id, $file);
                $vehicle->save();
            } elseif ($base64Payload !== null && trim($base64Payload) !== '') {
                $vehicle->image = $imageStorage->storeFromBase64Payload((int) $vehicle->id, $base64Payload);
                $vehicle->save();
            }

            $accountVehicle = null;
            if (!empty($validated['account_no'])) {
                $accountVehicle = new AccountVehicle();
                $accountVehicle->account_id = $validated['account_no'];
                $accountVehicle->vehicle_id = $vehicle->id;
                $accountVehicle->created_by = $createdBy;
                $accountVehicle->status = AccountVehicle::STATUS_ACTIVE;

                if (!$accountVehicle->save()) {
                    DB::rollBack();

                    return $this->sendError('Failed to link vehicle to account');
                }
                $accountVehicle->refresh();
            }

            DB::commit();

            return $this->sendResponse([
                'vehicle' => $vehicle->fresh(),
                'account_vehicle' => $accountVehicle,
            ], 'Vehicle created successfully');
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();

            return $this->sendError('Invalid vehicle image: ' . $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Vehicle creation failed: ' . $e->getMessage());

            return $this->sendError('Vehicle creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all vehicles with account details
     */
    public function getAllVehicles(Request $request): JsonResponse
    {
        try {
            $query = DB::table('vehicle')
                ->leftJoin('account', 'vehicle.account_no', '=', 'account.account_no')
                ->leftJoin('body_type', 'body_type.id', '=', 'vehicle.body_type_id')
                ->select([
                    'vehicle.exempted',
                    'account.account_no',
                    'vehicle.id',
                    'vehicle.status',
                    'vehicle.card_number',
                    'account.first_name',
                    'account.middle_name',
                    'account.surname',
                    'vehicle.body_type_id',
                    'vehicle.rfid_tag_no',
                    'vehicle.plate_no',
                    'body_type.name as body',
                    'vehicle.created_at'
                ]);

            // Apply search filter if provided
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('vehicle.plate_no', 'like', "%{$search}%")
                      ->orWhere('account.first_name', 'like', "%{$search}%")
                      ->orWhere('account.middle_name', 'like', "%{$search}%")
                      ->orWhere('account.surname', 'like', "%{$search}%")
                      ->orWhere('account.account_no', 'like', "%{$search}%")
                      ->orWhere('body_type.name', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'vehicle.created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Map frontend sort keys to database columns
            $sortColumnMap = [
                'plate_no' => 'vehicle.plate_no',
                'body' => 'body_type.name',
                'created_at' => 'vehicle.created_at',
            ];
            
            $sortColumn = $sortColumnMap[$sortBy] ?? 'vehicle.created_at';
            $query->orderBy($sortColumn, $sortOrder);

            // Pagination (cap page size for back-office safety / predictable DB load)
            $perPage = (int) $request->get('per_page', 15);
            $perPage = max(1, min($perPage, 100));
            $page = max(1, (int) $request->get('page', 1));

            $total = (clone $query)->count();
            $vehicles = $query->skip(($page - 1) * $perPage)
                              ->take($perPage)
                              ->get();

            $lastPage = ceil($total / $perPage);
            $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
            $to = min($page * $perPage, $total);

            return response()->json([
                'success' => true,
                'message' => 'Vehicles retrieved successfully',
                'data' => $vehicles,
                'pagination' => [
                    'current_page' => (int)$page,
                    'last_page' => $lastPage,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'from' => $from,
                    'to' => $to,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve vehicles: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve vehicles: ' . $e->getMessage());
        }
    }

    /**
     * Search a single vehicle by plate or id and return the same detail payload as GET /vehicles/{id}.
     *
     * Query: exactly one of `plate_no` or `vehicle_id`. If both are sent, `vehicle_id` wins.
     */
    public function searchVehicleDetails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'required_without:vehicle_id|nullable|string',
            'vehicle_id' => 'required_without:plate_no|nullable|integer|exists:vehicle,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = null;
            if ($request->filled('vehicle_id')) {
                $vehicle = Vehicle::find((int) $request->get('vehicle_id'));
            } elseif ($request->filled('plate_no')) {
                $vehicle = Vehicle::where('plate_no', trim((string) $request->get('plate_no')))->first();
            }

            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 0, 404);
            }

            return $this->sendResponse(
                $this->buildVehicleDetailPayload($vehicle),
                'Vehicle details retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Vehicle lookup failed: ' . $e->getMessage());

            return $this->sendError('Failed to retrieve vehicle details: ' . $e->getMessage());
        }
    }

    /**
     * Latest toll crossings (top 100). Single query, ordered by primary key (uses clustered index).
     * Use `vehicle_id` with GET /api/vehicles/{id} when present.
     */
    public function recentTollTransactions(): JsonResponse
    {
        try {
            $rows = DB::table('toll_transaction as tt')
                ->leftJoin('vehicle as v', 'tt.vehicle_id', '=', 'v.id')
                ->leftJoin('body_type as bt', 'tt.body_type_id', '=', 'bt.id')
                ->leftJoin('lane as l', 'tt.lane_id', '=', 'l.id')
                ->select([
                    'tt.id as toll_transaction_id',
                    'v.id as vehicle_id',
                    'tt.vehicle_id as toll_vehicle_id_raw',
                    DB::raw('COALESCE(NULLIF(v.plate_no, ""), tt.plate_no) as plate_number'),
                    'tt.created_at as crossed_at',
                    'l.id as lane_id',
                    'l.lane_no as lane_passed',
                    'tt.lane_id as lane_reference',
                    'bt.id as body_type_id',
                    'bt.name as body_type_name',
                    'tt.charged_amount',
                    'tt.trans_type',
                    'tt.exemption',
                    'tt.status',
                    'tt.receipt_num',
                    'tt.account_no',
                    'tt.shift_id',
                ])
                ->orderByDesc('tt.id')
                ->limit(100)
                ->get();

            return $this->sendResponse([
                'transactions' => $rows,
                'count' => $rows->count(),
            ], 'Recent toll transactions retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('recentTollTransactions failed: ' . $e->getMessage());

            return $this->sendError('Failed to retrieve toll transactions: ' . $e->getMessage());
        }
    }

    /**
     * Get vehicle details by ID
     */
    public function getVehicleById($id): JsonResponse
    {
        try {
            $vehicle = Vehicle::find($id);

            if (!$vehicle) {
                return $this->sendError('Vehicle not found');
            }

            return $this->sendResponse(
                $this->buildVehicleDetailPayload($vehicle),
                'Vehicle details retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve vehicle details: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve vehicle details: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVehicleDetailPayload(Vehicle $vehicle): array
    {
        $bodyType = BodyType::find($vehicle->body_type_id);

        $price = PriceList::where('body_type_id', $vehicle->body_type_id)->first();

        $accountData = null;
        if ($vehicle->account_no) {
            $account = Account::where('account_no', $vehicle->account_no)->first();
            if ($account) {
                $accountData = [
                    'account_no' => $account->account_no,
                    'first_name' => $account->first_name,
                    'middle_name' => $account->middle_name,
                    'surname' => $account->surname,
                    'full_name' => trim(($account->first_name ?? '') . ' ' . ($account->middle_name ?? '') . ' ' . ($account->surname ?? '')),
                    'phone' => $account->phone ?? null,
                    'email' => $account->email ?? null,
                    'nida' => $account->nida ?? null,
                    'account_balance' => $account->account_balance ?? 0,
                ];
            }
        }

        $responseData = [
            'vehicleID' => $vehicle->id,
            'plate_no' => $vehicle->plate_no,
            'card_number' => $vehicle->card_number,
            'rfid_tag_no' => $vehicle->rfid_tag_no,
            'body_type_id' => $vehicle->body_type_id,
            'account_no' => $vehicle->account_no,
            'exempted' => $vehicle->exempted,
            'exempt_reason' => $vehicle->exempt_reason,
            'status' => $vehicle->status,
            'created_at' => $vehicle->created_at,
            'updated_at' => $vehicle->updated_at,
            'name' => $bodyType ? $bodyType->name : null,
            'body' => $bodyType ? $bodyType->id : null,
            'account_account_no' => $accountData ? $accountData['account_no'] : null,
            'first_name' => $accountData ? $accountData['first_name'] : null,
            'middle_name' => $accountData ? $accountData['middle_name'] : null,
            'surname' => $accountData ? $accountData['surname'] : null,
            'phone' => $accountData ? $accountData['phone'] : null,
            'email' => $accountData ? $accountData['email'] : null,
            'nida' => $accountData ? $accountData['nida'] : null,
            'account_balance' => $accountData ? $accountData['account_balance'] : null,
            'price' => $price ? $price->amount : null,
            'account' => $accountData,
        ];

        $responseData['image'] = Vehicle::getImageBase64($vehicle->id);

        return $responseData;
    }

    /**
     * Update vehicle details
     */
    public function updateVehicle(Request $request): JsonResponse
    {
        $payload = $this->vehicleUpdatePayload($request);
        $vehicleId = $payload['vehicle_id'] ?? null;

        if ($parseError = $this->jsonBodyParseErrorResponse($request, $payload, ['vehicle_id', 'plate_no', 'body_type_id'])) {
            return $parseError;
        }

        $validator = $this->validateWithVehicleImageExplained($request, $payload, [
            'vehicle_id' => 'required|integer|exists:vehicle,id',
            'plate_no' => 'required|string|unique:vehicle,plate_no,' . $vehicleId,
            'account_no' => 'nullable|string|exists:account,account_no|required_with:account_vehicle_id',
            'body_type_id' => 'required|integer|exists:body_type,id',
            'card_number' => 'nullable|string',
            'rfid_tag_no' => 'nullable|string',
            'account_vehicle_id' => 'nullable|integer|exists:account_vehicle,id',
            'vehicle_image' => $this->vehicleImageFieldRules($request),
            'vehicle_image_base64' => 'nullable|string|max:' . (int) config('vehicle.images.max_base64_chars', 30000000),
            'clear_vehicle_image' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $validated = $validator->validated();
            $result = app(VehicleUpdateService::class)->updateWithAccountVehicle(
                $validated,
                $request->file('vehicle_image'),
                $this->resolvedVehicleImageBase64Payload($validated),
                $request->boolean('clear_vehicle_image')
            );

            return $this->sendResponse([
                'vehicle' => $result['vehicle'],
                'account_vehicle' => $result['account_vehicle'],
            ], 'Vehicle updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->sendError('Invalid vehicle image: ' . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Vehicle update failed: ' . $e->getMessage());

            return $this->sendError('Vehicle update failed: ' . $e->getMessage());
        }
    }

    /**
     * Activate vehicle
     */
    public function activateVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:vehicle,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = Vehicle::find($request->id);
            $vehicle->status = 1;
            $vehicle->save();

            return $this->sendResponse($vehicle, 'Vehicle activated successfully');

        } catch (\Exception $e) {
            Log::error('Vehicle activation failed: ' . $e->getMessage());
            return $this->sendError('Vehicle activation failed: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate vehicle
     */
    public function deactivateVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:vehicle,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = Vehicle::find($request->id);
            $vehicle->status = 0;
            $vehicle->save();

            return $this->sendResponse($vehicle, 'Vehicle deactivated successfully');

        } catch (\Exception $e) {
            Log::error('Vehicle deactivation failed: ' . $e->getMessage());
            return $this->sendError('Vehicle deactivation failed: ' . $e->getMessage());
        }
    }


    /**
     * Clear vehicle image (set image field to null) by plate number
     */
    public function clearVehicleImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'required|string|exists:vehicle,plate_no',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = Vehicle::where('plate_no', $request->plate_no)->first();

            if (!$vehicle) {
                return $this->sendError('Vehicle not found', ['plate_no' => $request->plate_no]);
            }

            $vehicle->image = null;
            $vehicle->save();

            return $this->sendResponse([
                'plate_no' => $vehicle->plate_no,
                'image' => $vehicle->image,
            ], 'Vehicle image cleared successfully');

        } catch (\Exception $e) {
            Log::error('Failed to clear vehicle image: ' . $e->getMessage(), [
                'plate_no' => $request->plate_no ?? 'not provided',
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to clear vehicle image: ' . $e->getMessage());
        }
    }

    /**
     * Query vehicle with pricing information
     */
    public function queryVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = DB::table('vehicle')
                ->join('body_type', 'vehicle.body_type_id', '=', 'body_type.id')
                ->join('price_list', 'body_type.id', '=', 'price_list.body_type_id')
                ->select([
                    'plate_no',
                    'body_type.name',
                    'price_list.amount',
                    'exempted',
                    'image'
                ])
                ->where('vehicle.plate_no', $request->plate_no)
                ->first();

            if (!$vehicle) {
                return $this->sendError('Vehicle not found');
            }

            return $this->sendResponse($vehicle, 'Vehicle query successful');

        } catch (\Exception $e) {
            Log::error('Vehicle query failed: ' . $e->getMessage());
            return $this->sendError('Vehicle query failed: ' . $e->getMessage());
        }
    }

    /**
     * Exempt vehicle from toll charges
     */
    public function exemptVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:vehicle,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = Vehicle::find($request->id);
            $vehicle->exempted = 1;
            $vehicle->save();

            return $this->sendResponse($vehicle, 'Vehicle exempted successfully');

        } catch (\Exception $e) {
            Log::error('Vehicle exemption failed: ' . $e->getMessage());
            return $this->sendError('Vehicle exemption failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove vehicle exemption
     */
    public function removeExemptedVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:vehicle,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
        }

        try {
            $vehicle = Vehicle::find($request->id);
            $vehicle->exempted = 0;
            $vehicle->save();

            return $this->sendResponse($vehicle, 'Vehicle exemption removed successfully');

        } catch (\Exception $e) {
            Log::error('Vehicle exemption removal failed: ' . $e->getMessage());
            return $this->sendError('Vehicle exemption removal failed: ' . $e->getMessage());
        }
    }

    /**
     * Get vehicles by account ID with pagination
     */
    public function getVehiclesByAccountId($accountId, Request $request): JsonResponse
    {
        try {
            // Validate account exists and get account number
            $account = Account::find($accountId);
            if (!$account) {
                return $this->sendError('Account not found', [], 404);
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');

            // Build query
            $query = Vehicle::where('account_no', $account->account_no)
                ->with(['bodyType']);

            // Add search filter
            if (!empty($search)) {
                $query->where('plate_no', 'like', '%' . $search . '%');
            }

            // Get paginated results
            $vehicles = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform the data
            $transformedVehicles = $vehicles->getCollection()->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no,
                    'body_type' => $vehicle->bodyType ? [
                        'id' => $vehicle->bodyType->id,
                        'name' => $vehicle->bodyType->name,
                        'description' => $vehicle->bodyType->description
                    ] : null,
                    'status' => $vehicle->status,
                    'exempted' => $vehicle->exempted,
                    'created_at' => $vehicle->created_at,
                    'updated_at' => $vehicle->updated_at
                ];
            });

            // Return paginated response
            return $this->sendResponse([
                'vehicles' => $transformedVehicles,
                'pagination' => [
                    'current_page' => $vehicles->currentPage(),
                    'last_page' => $vehicles->lastPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'from' => $vehicles->firstItem(),
                    'to' => $vehicles->lastItem()
                ]
            ], 'Vehicles retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve vehicles for account: ' . $e->getMessage(), [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to retrieve vehicles: ' . $e->getMessage());
        }
    }

    /**
     * Fetch vehicle details by plate number
     */
    public function fetchVehicle(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_no' => 'required|string',
                'source' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', ['errors' => $validator->errors()], 400, 422);
            }

            $plateNumber = $request->plate_no;
            $source = $request->source;

            // Check authorization
            if ($source !== 'Bridge-Portal') {
                return $this->sendError('You are not authorized', [], 401);
            }

            // Find vehicle
            $vehicle = Vehicle::where('plate_no', $plateNumber)->first();
            
            if (!$vehicle) {
                return $this->sendError('No vehicle record found', [], 404);
            }

            // Get body type
            $bodyType = BodyType::find($vehicle->body_type_id);
            
            // Get price
            $price = PriceList::where('body_type_id', $vehicle->body_type_id)->first();
            
            // Get account data if exists
            $accountData = null;
            if ($vehicle->account_no) {
                $accountData = Account::where('account_no', $vehicle->account_no)->first();
            }

            // Handle vehicle image
            $imageb64 = null;
            if ($vehicle->image) {
                // Try to get image from vehicle's image field
                $path = $vehicle->image;
                if (file_exists($path)) {
                    $data = file_get_contents($path);
                    $imageb64 = base64_encode($data);
                }
            }
            
            // If no image found, try to get from vehicle ID
            if (!$imageb64) {
                $imageData = Vehicle::getImageContent($vehicle->id);
                if ($imageData) {
                    $imageb64 = base64_encode($imageData);
                }
            }
            
            // If still no image, use default car image
            if (!$imageb64) {
                $defaultPath = public_path('images/car.png');
                if (file_exists($defaultPath)) {
                    $data = file_get_contents($defaultPath);
                    $imageb64 = base64_encode($data);
                }
            }

            return $this->sendResponse([
                'vehicle' => [
                    'id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no,
                    'body_type_id' => $vehicle->body_type_id,
                    'body_type' => $bodyType ? $bodyType->name : null,
                    'account_no' => $vehicle->account_no,
                    'exempted' => $vehicle->exempted,
                    'exempt_reason' => $vehicle->exempt_reason,
                    'status' => $vehicle->status,
                    'created_at' => $vehicle->created_at,
                    'updated_at' => $vehicle->updated_at
                ],
                'account' => $accountData,
                'price' => $price ? $price->amount : null,
                'image' => $imageb64
            ], 'Vehicle details found');

        } catch (\Exception $e) {
            Log::error('Failed to fetch vehicle: ' . $e->getMessage(), [
                'plate_no' => $request->plate_no ?? 'not provided',
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to fetch vehicle: ' . $e->getMessage());
        }
    }
    public function fetchNormaPassages(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_no' => 'required|string|exists:vehicle,plate_no',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);

            $query = DB::table('toll_transaction as tt')
                ->select([
                    'tt.id',
                    'tt.charged_amount as amount',
                    'tt.plate_no',
                    'tt.receipt_num as receipt_number',
                    'tt.trans_type',
                    'tt.created_at as passage_time',
                    'l.lane_no',
                    'bt.name as body_type_name'
                ])
                ->join('lane as l', 'l.id', '=', 'tt.lane_id')
                ->join('body_type as bt', 'bt.id', '=', 'tt.body_type_id')
                ->where('tt.plate_no', '=', $request->plate_no)
                ->orderBy('tt.created_at', 'desc');

            // Debug: Log the raw SQL query
            Log::info('Normal passages SQL query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'plate_no' => $request->plate_no
            ]);

            // Get total count for pagination
            $total = $query->count();

            // Get paginated results
            $passages = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Transform data to match frontend expectations
            $transformedPassages = $passages->map(function ($passage) {
                return [
                    'id' => $passage->id,
                    'plate_no' => $passage->plate_no,
                    'lane_no' => $passage->lane_no,
                    'amount' => $passage->amount,
                    'passage_time' => $passage->passage_time,
                    'receipt_number' => $passage->receipt_number,
                    'status' => 'completed', // Default status for normal passages
                    'created_at' => $passage->passage_time,
                    'updated_at' => $passage->passage_time
                ];
            });

            return $this->sendResponse([
                'passages' => $transformedPassages,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => ($page - 1) * $perPage + 1,
                    'to' => min($page * $perPage, $total)
                ]
            ], 'Normal passages fetched successfully');

        } catch (\Exception $e) {
            Log::error('Failed to fetch normal passages: ' . $e->getMessage(), [
                'plate_no' => $request->plate_no ?? 'not provided',
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to fetch normal passages: ' . $e->getMessage());
        }
    }

    public function fetchBundlePassages(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_no' => 'required|string|exists:vehicle,plate_no',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);

            $query = DB::table('bundle_subscription_passage as bsp')
                ->select([
                    'bsp.id',
                    'l.lane_no',
                    'v.plate_no',
                    'bt.name as body_type_name',
                    'bsp.arrival_time',
                    'bsp.clearance_time',
                    'bsp.created_at'
                ])
                ->join('lane as l', 'l.id', '=', 'bsp.lane_id')
                ->join('vehicle as v', 'v.card_number', '=', 'bsp.card_number')
                ->join('body_type as bt', 'v.body_type_id', '=', 'bt.id')
                ->where('v.plate_no', '=', $request->plate_no)
                ->orderBy('bsp.created_at', 'desc');

            // Get total count for pagination
            $total = $query->count();

            // Get paginated results
            $passages = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Transform data to match frontend expectations
            $transformedPassages = $passages->map(function ($passage) {
                return [
                    'id' => $passage->id,
                    'plate_no' => $passage->plate_no,
                    'lane_no' => $passage->lane_no,
                    'amount' => 0, // Bundle passages don't have individual amounts
                    'passage_time' => $passage->arrival_time,
                    'receipt_number' => 'BUNDLE-' . $passage->id, // Generate receipt number for bundle
                    'status' => 'completed', // Default status for bundle passages
                    'created_at' => $passage->created_at,
                    'updated_at' => $passage->created_at
                ];
            });

            return $this->sendResponse([
                'passages' => $transformedPassages,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => ($page - 1) * $perPage + 1,
                    'to' => min($page * $perPage, $total)
                ]
            ], 'Bundle passages fetched successfully');

        } catch (\Exception $e) {
            Log::error('Failed to fetch bundle passages: ' . $e->getMessage(), [
                'plate_no' => $request->plate_no ?? 'not provided',
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to fetch bundle passages: ' . $e->getMessage());
        }
    }



    /**
     * Paid bill = bridge_bills row has both trx_id and trx_dt_tm populated (payment postback).
     *
     * @param  object  $subscription  query row
     */
    private function bridgeBillHasRecordedPaymentTransaction(object $subscription): bool
    {
        $trxId = isset($subscription->trx_id) ? trim((string) $subscription->trx_id) : '';
        if ($trxId === '') {
            return false;
        }

        if (!isset($subscription->trx_dt_tm) || $subscription->trx_dt_tm === null) {
            return false;
        }

        return trim((string) $subscription->trx_dt_tm) !== '';
    }

    public function fetchBundleSubscriptions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plate_no' => 'required|string|exists:vehicle,plate_no',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()]);
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);

            // Try the full query first
            try {
                $query = DB::table('bundle_subscriptions as bs')
                    ->select([
                        'bs.bill_id',
                        'bs.bundle_id',
                        'bb.contr_num',
                        'bb.is_cancelled',
                        'bb.bill_status',
                        'bb.trx_id',
                        'bb.trx_dt_tm',
                        'bb.bill_desc',
                        'bb.bill_gen_at',
                        'bb.bill_exp_dt',
                        'bb.psp_receipt_num',
                        'bb.receipt_number',
                        'bb.source',
                        'bb.cancel_reason',
                        'bb.bill_cancel_date',
                        'bb.bill_gen_by',
                        'tb.bundle_description',
                        'bs.start_date',
                        'bs.expire_date',
                        'v.plate_no',
                        'tb.status as bundle_status',
                        'bb.bill_amount'
                    ])
                    ->join('vehicle as v', 'v.id', '=', 'bs.vehicle_id')
                    ->join('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
                    ->join('bridge_bills as bb', 'bb.id', '=', 'bs.bill_id')
                    ->where('v.plate_no', '=', $request->plate_no)
                    ->orderBy('bs.start_date', 'desc');
            } catch (\Exception $e) {
                // Fallback to simpler query if joins fail
                Log::warning('Full bundle subscriptions query failed, using fallback', [
                    'error' => $e->getMessage(),
                    'plate_no' => $request->plate_no
                ]);
                
                $query = DB::table('bundle_subscriptions as bs')
                    ->select([
                        'bs.id',
                        'bs.bill_id',
                        'bs.bundle_id',
                        'bs.start_date',
                        'bs.expire_date',
                        'bs.amount',
                        'bs.status',
                        'v.plate_no'
                    ])
                    ->join('vehicle as v', 'v.id', '=', 'bs.vehicle_id')
                    ->where('v.plate_no', '=', $request->plate_no)
                    ->orderBy('bs.start_date', 'desc');
            }

            // Debug: Log the raw SQL query
            Log::info('Bundle subscriptions SQL query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'plate_no' => $request->plate_no
            ]);

            // Get total count for pagination
            $total = $query->count();
            
            // Debug logging
            Log::info('Bundle subscriptions query debug', [
                'plate_no' => $request->plate_no,
                'total_count' => $total,
                'per_page' => $perPage,
                'page' => $page
            ]);

            // Get paginated results
            $subscriptions = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Debug: Log raw subscription data
            Log::info('Raw bundle subscriptions data', [
                'plate_no' => $request->plate_no,
                'subscriptions_count' => $subscriptions->count(),
                'raw_data' => $subscriptions->toArray()
            ]);

            $transformedSubscriptions = $subscriptions->map(function ($subscription) {
                $hasPaidTransaction = $this->bridgeBillHasRecordedPaymentTransaction($subscription);

                // Human-readable status (legacy UI)
                $status = 'Unknown';
                if (isset($subscription->is_cancelled) && $subscription->is_cancelled) {
                    $status = 'Cancelled';
                } elseif ($hasPaidTransaction) {
                    $status = 'Paid';
                } elseif (isset($subscription->bill_id) && $subscription->bill_id !== null && $subscription->bill_id !== '') {
                    $status = 'Pending';
                } elseif (isset($subscription->status)) {
                    $status = $subscription->status;
                }

                // API-friendly payment_status (aligned with GET /api/bundle-purchases)
                $paymentStatus = 'unknown';
                if (!isset($subscription->bill_id) || $subscription->bill_id === null || $subscription->bill_id === '') {
                    $paymentStatus = 'no_bill';
                } elseif (isset($subscription->is_cancelled) && $subscription->is_cancelled) {
                    $paymentStatus = 'cancelled';
                } elseif ($hasPaidTransaction) {
                    $paymentStatus = 'paid';
                } elseif (isset($subscription->bill_id) && $subscription->bill_id !== null && $subscription->bill_id !== '') {
                    $paymentStatus = 'pending';
                }

                $isCancelled = isset($subscription->is_cancelled) && $subscription->is_cancelled;

                return [
                    'id' => $subscription->bill_id ?? $subscription->id,
                    'bill_id' => $subscription->bill_id ?? $subscription->id,
                    'plate_no' => $subscription->plate_no,
                    'bundle_name' => $subscription->bundle_description ?? 'Bundle #' . ($subscription->bundle_id ?? 'Unknown'),
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->expire_date,
                    'amount' => $subscription->bill_amount ?? $subscription->amount ?? 0,
                    'bill_amount' => $subscription->bill_amount ?? $subscription->amount ?? 0,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'control_number' => isset($subscription->contr_num) && $subscription->contr_num !== null && $subscription->contr_num !== ''
                        ? $subscription->contr_num
                        : null,
                    'contract_number' => isset($subscription->contr_num) && $subscription->contr_num !== null && $subscription->contr_num !== ''
                        ? $subscription->contr_num
                        : null,
                    'trx_id' => $subscription->trx_id ?? null,
                    'trx_dt_tm' => $subscription->trx_dt_tm ?? null,
                    'bundle_status' => $subscription->bundle_status ?? 'N/A',
                    'bill_description' => $subscription->bill_desc ?? ($subscription->bundle_description ?? null),
                    'bill_generated_at' => $subscription->bill_gen_at ?? null,
                    'bill_expiry_at' => $subscription->bill_exp_dt ?? null,
                    'psp_receipt_num' => $subscription->psp_receipt_num ?? null,
                    'receipt_number' => $subscription->receipt_number ?? null,
                    'source' => $subscription->source ?? null,
                    'account_no' => $subscription->bill_gen_by ?? null,
                    'cancel_reason' => $subscription->cancel_reason ?? null,
                    'bill_cancel_date' => $subscription->bill_cancel_date ?? null,
                    'is_cancelled' => $isCancelled ? 1 : 0,
                    'can_cancel' => !$isCancelled && !$hasPaidTransaction,
                    'can_repost' => !$isCancelled && !$hasPaidTransaction,
                    'can_print' => $hasPaidTransaction || !empty($subscription->psp_receipt_num),
                    'created_at' => $subscription->bill_gen_at ?? $subscription->start_date,
                    'updated_at' => $subscription->start_date,
                ];
            });

            // Debug: Log transformed subscription data
            Log::info('Transformed bundle subscriptions data', [
                'plate_no' => $request->plate_no,
                'transformed_count' => $transformedSubscriptions->count(),
                'transformed_data' => $transformedSubscriptions->toArray()
            ]);

            return $this->sendResponse([
                'passages' => $transformedSubscriptions, // Using 'passages' key for consistency
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => ($page - 1) * $perPage + 1,
                    'to' => min($page * $perPage, $total)
                ]
            ], 'Bundle subscriptions fetched successfully');

        } catch (\Exception $e) {
            Log::error('Failed to fetch bundle subscriptions: ' . $e->getMessage(), [
                'plate_no' => $request->plate_no ?? 'not provided',
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to fetch bundle subscriptions: ' . $e->getMessage());
        }
    }

    /**
     * Remove vehicle after validating it is not referenced in dependent tables.
     */
    public function removeVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plate_no' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first(), ['errors' => $validator->errors()], 400, 422);
        }

        try {
            $vehicle = Vehicle::where('plate_no', $request->plate_no)->first();
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', ['plate_no' => $request->plate_no], 404, 404);
            }

            $blockingReferences = [];

            $vehicleIdTables = [
                'account_vehicle',
                'transactions',
                // 'transactions_copy',
                // 'toll_transaction',
                // 'toll_transaction_1',
                'bundle_subscriptions',
            ];

            foreach ($vehicleIdTables as $table) {
                $count = DB::table($table)
                    ->where('vehicle_id', $vehicle->id)
                    ->count();

                if ($count > 0) {
                    $blockingReferences[] = [
                        'table' => $table,
                        'column' => 'vehicle_id',
                        'matches' => $count,
                    ];
                }
            }


            $distParamTables = [
                'bridge_bills',
            ];

            foreach ($distParamTables as $table) {
                $count = DB::table($table)
                    ->where('dist_param', $vehicle->plate_no)
                    ->count();

                if ($count > 0) {
                    $blockingReferences[] = [
                        'table' => $table,
                        'column' => 'dist_param',
                        'matches' => $count,
                    ];
                }
            }

            if (!empty($vehicle->card_number)) {
                $cardReferenceCount = DB::table('bundle_subscription_passage')
                    ->where('card_number', $vehicle->card_number)
                    ->count();

                if ($cardReferenceCount > 0) {
                    $blockingReferences[] = [
                        'table' => 'bundle_subscription_passage',
                        'column' => 'card_number',
                        'matches' => $cardReferenceCount,
                    ];
                }
            }

            if (!empty($blockingReferences)) {
                return $this->sendError(
                    'Vehicle cannot be removed because related records exist',
                    [
                        'plate_no' => $vehicle->plate_no,
                        'vehicle_id' => $vehicle->id,
                        'card_number' => $vehicle->card_number,
                        'blocking_references' => $blockingReferences,
                    ],
                    409,
                    409
                );
            }

            DB::beginTransaction();
            $vehicleData = [
                'id' => $vehicle->id,
                'plate_no' => $vehicle->plate_no,
                'card_number' => $vehicle->card_number,
            ];
            $vehicle->delete();
            DB::commit();

            return $this->sendResponse($vehicleData, 'Vehicle removed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove vehicle: ' . $e->getMessage(), [
                'plate_no' => $request->plate_no ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to remove vehicle: ' . $e->getMessage(), [], 500, 500);
        }
    }
}
