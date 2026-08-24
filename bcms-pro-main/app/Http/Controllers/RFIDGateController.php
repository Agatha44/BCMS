<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\BundleSubscription;
use App\Models\TollTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RFIDGateController extends BasicController
{
    /**
     * Process RFID tag and control gate access
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processRFIDAccess(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'rfid_tag_no' => 'required|string',
                'lane_id' => 'required|integer'
            ]);

            $rfidTagNo = $request->input('rfid_tag_no');
            $laneId = $request->input('lane_id');

            Log::info('RFID Gate Access Request', [
                'rfid_tag_no' => $rfidTagNo,
                'lane_id' => $laneId
            ]);

            // Step 1: Find vehicle by RFID tag
            $vehicle = Vehicle::where('rfid_tag_no', $rfidTagNo)->first();
            
            if (!$vehicle) {
                Log::warning('Vehicle not found for RFID tag', [
                    'rfid_tag_no' => $rfidTagNo
                ]);
                
                return $this->sendError('Vehicle not found for RFID tag', [
                    'rfid_tag_no' => $rfidTagNo
                ], 404);
            }

            Log::info('Vehicle found', [
                'vehicle_id' => $vehicle->id,
                'plate_no' => $vehicle->plate_no,
                'rfid_tag_no' => $vehicle->rfid_tag_no
            ]);

            // Step 2: Check for active bundle subscription
            $activeSubscription = BundleSubscription::where('vehicle_id', $vehicle->id)
                ->where('status', BundleSubscription::STATUS_ACTIVE)
                ->where('expire_date', '>', now())
                ->with(['tollBundle', 'account'])
                ->first();

            if (!$activeSubscription) {
                Log::warning('No active bundle subscription found', [
                    'vehicle_id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no
                ]);

                // Log failed access attempt (no database record for failed bundle access)
                Log::warning('Bundle access denied - no active subscription', [
                    'vehicle_id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no,
                    'rfid_tag_no' => $rfidTagNo,
                    'lane_id' => $laneId
                ]);

                return $this->sendError('No active bundle subscription found', [
                    'vehicle_id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no,
                    'rfid_tag_no' => $rfidTagNo
                ], 403);
            }

            Log::info('Active bundle subscription found', [
                'vehicle_id' => $vehicle->id,
                'bundle_id' => $activeSubscription->bundle_id,
                'bundle_name' => $activeSubscription->tollBundle->name ?? 'Unknown',
                'expire_date' => $activeSubscription->expire_date
            ]);

            // Step 3: Record successful bundle passage
            $passageId = $this->recordBundlePassage(
                $vehicle, 
                $activeSubscription,
                $laneId
            );

            // Step 4: Prepare gate control response
            $responseData = [
                'access_granted' => true,
                'vehicle' => [
                    'id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no,
                    'rfid_tag_no' => $vehicle->rfid_tag_no,
                    'body_type_id' => $vehicle->body_type_id
                ],
                'bundle_subscription' => [
                    'id' => $activeSubscription->id,
                    'bundle_id' => $activeSubscription->bundle_id,
                    'bundle_name' => $activeSubscription->tollBundle->name ?? 'Unknown',
                    'start_date' => $activeSubscription->start_date,
                    'expire_date' => $activeSubscription->expire_date,
                    'status' => $activeSubscription->status
                ],
                'account' => [
                    'account_no' => $activeSubscription->account->account_no ?? null,
                    'full_name' => $activeSubscription->account->full_name ?? null
                ],
                'passage' => [
                    'id' => $passageId,
                    'type' => 'bundle_subscription'
                ],
                'gate_control' => [
                    'action' => 'open',
                    'message' => 'Gate should be opened'
                ]
            ];

            Log::info('RFID access granted successfully', [
                'vehicle_id' => $vehicle->id,
                'passage_id' => $passageId
            ]);

            return $this->sendResponse($responseData, 'Access granted - Gate should be opened');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in RFID gate access', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return $this->sendError('Validation failed', $e->errors(), 422);
            
        } catch (\Exception $e) {
            Log::error('Error processing RFID gate access', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return $this->sendError('Internal server error occurred while processing RFID access', [
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record bundle subscription passage in database
     * 
     * @param Vehicle $vehicle
     * @param BundleSubscription $activeSubscription
     * @param int $laneId
     * @return int|null
     */
    private function recordBundlePassage($vehicle, $activeSubscription, $laneId)
    {
        try {
            DB::beginTransaction();

            // Record bundle subscription passage
            $passageId = DB::table('bundle_subscription_passage')->insertGetId([
                'card_number' => $vehicle->card_number ?? $vehicle->account_no, // Use card_number or fallback to account_no
                'lane_id' => $laneId, // Use lane_id directly from POS
                'arrival_time' => now()->toDateTimeString(),
                'clearance_time' => now()->toDateTimeString(), // Same as arrival for bundle passages
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
                'created_by' => 1, // System user
                'updated_by' => 1, // System user
                'shift_id' => null // Can be set if shift tracking is needed
            ]);

            // Log the bundle passage
            Log::info('Bundle subscription passage recorded', [
                'vehicle_id' => $vehicle->id,
                'passage_id' => $passageId,
                'bundle_subscription_id' => $activeSubscription->id,
                'lane_id' => $laneId
            ]);

            DB::commit();
            return $passageId;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record bundle passage', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }



    /**
     * Get vehicle information by RFID tag (for testing/debugging)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVehicleByRFID(Request $request)
    {
        try {
            $request->validate([
                'rfid_tag_no' => 'required|string'
            ]);

            $rfidTagNo = $request->input('rfid_tag_no');
            
            $vehicle = Vehicle::where('rfid_tag_no', $rfidTagNo)
                ->with(['bodyType', 'account'])
                ->first();

            if (!$vehicle) {
                return $this->sendError('Vehicle not found for RFID tag', [
                    'rfid_tag_no' => $rfidTagNo
                ], 404);
            }

            // Get active bundle subscription
            $activeSubscription = BundleSubscription::where('vehicle_id', $vehicle->id)
                ->where('status', BundleSubscription::STATUS_ACTIVE)
                ->where('expire_date', '>', now())
                ->with(['tollBundle', 'account'])
                ->first();

            $responseData = [
                'vehicle' => [
                    'id' => $vehicle->id,
                    'plate_no' => $vehicle->plate_no,
                    'rfid_tag_no' => $vehicle->rfid_tag_no,
                    'body_type_id' => $vehicle->body_type_id,
                    'account_no' => $vehicle->account_no,
                    'created_at' => $vehicle->created_at,
                    'updated_at' => $vehicle->updated_at
                ],
                'body_type' => $vehicle->bodyType ? [
                    'id' => $vehicle->bodyType->id,
                    'name' => $vehicle->bodyType->name
                ] : null,
                'account' => $vehicle->account ? [
                    'account_no' => $vehicle->account->account_no,
                    'full_name' => $vehicle->account->full_name
                ] : null,
                'active_bundle_subscription' => $activeSubscription ? [
                    'id' => $activeSubscription->id,
                    'bundle_id' => $activeSubscription->bundle_id,
                    'bundle_name' => $activeSubscription->tollBundle->name ?? 'Unknown',
                    'start_date' => $activeSubscription->start_date,
                    'expire_date' => $activeSubscription->expire_date,
                    'status' => $activeSubscription->status
                ] : null
            ];

            return $this->sendResponse($responseData, 'Vehicle information retrieved successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving vehicle by RFID', [
                'error' => $e->getMessage(),
                'rfid_tag_no' => $request->input('rfid_tag_no')
            ]);
            
            return $this->sendError('Internal server error', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
