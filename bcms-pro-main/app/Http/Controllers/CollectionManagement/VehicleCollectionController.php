<?php

namespace App\Http\Controllers\CollectionManagement;

use App\Http\Controllers\Configurations\ConfigurationController;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Vehicle\VehicleUpdateService;

class VehicleCollectionController extends ConfigurationController
{
    private const MAX_PER_PAGE = 100;

    /**
     * Paginated list for collection management (no image payload).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = max(1, min((int) $request->get('per_page', 15), self::MAX_PER_PAGE));
            $page = max(1, (int) $request->get('page', 1));

            $query = Vehicle::query()
                ->with(['bodyType:id,name'])
                ->orderByDesc('id');

            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where('plate_no', 'like', '%' . $search . '%');
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()->map(function (Vehicle $vehicle) {
                return $this->transformListRow($vehicle);
            });

            return $this->sendResponse([
                'vehicles' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ], 'Vehicles retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Collection management vehicle list failed: ' . $e->getMessage());

            return $this->sendError('Failed to retrieve vehicles: ' . $e->getMessage());
        }
    }

    /**
     * Single vehicle for collection management, including base64 image (see Vehicle::getCollectionManagementImagePayload).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $vehicle = Vehicle::with(['bodyType:id,name,description'])->find($id);
            if (!$vehicle) {
                return $this->sendError('Vehicle not found', [], 0, 404);
            }

            $image = Vehicle::getCollectionManagementImagePayload((int) $vehicle->id);

            $data = [
                'id' => $vehicle->id,
                'plate_no' => $vehicle->plate_no,
                'account_no' => $vehicle->account_no,
                'body_type' => $vehicle->bodyType ? [
                    'id' => $vehicle->bodyType->id,
                    'name' => $vehicle->bodyType->name,
                    'description' => $vehicle->bodyType->description,
                ] : null,
                'registration_date' => $vehicle->created_at,
                'status' => (int) $vehicle->status,
                'status_label' => ((int) $vehicle->status) === 1 ? 'ACTIVE' : 'INACTIVE',
                'exemption' => (int) $vehicle->exempted,
                'exemption_label' => ((int) $vehicle->exempted) === 1 ? 'EXEMPTED' : 'NOT EXEMPTED',
                'exempt_reason' => $vehicle->exempt_reason,
                'card_number' => $vehicle->card_number,
                'rfid_tag_no' => $vehicle->rfid_tag_no,
                'updated_at' => $vehicle->updated_at,
                'image' => $image,
            ];

            return $this->sendResponse($data, 'Vehicle retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Collection management vehicle detail failed: ' . $e->getMessage());

            return $this->sendError('Failed to retrieve vehicle: ' . $e->getMessage());
        }
    }

    /**
     * Update vehicle (same payload as PUT /api/vehicles/update). Image: multipart `vehicle_image` (POST recommended), or JSON `vehicle_image` as base64/data URI, or `vehicle_image_base64`; `clear_vehicle_image` true clears. Saved file + path on `vehicle.image` (see VehicleImageStorageService).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payload = $this->vehicleUpdatePayload($request, $id);

        if ($parseError = $this->jsonBodyParseErrorResponse($request, $payload, ['vehicle_id', 'plate_no', 'body_type_id'])) {
            return $parseError;
        }

        $validator = $this->validateWithVehicleImageExplained($request, $payload, [
            'vehicle_id' => 'required|integer|exists:vehicle,id',
            'plate_no' => 'required|string|unique:vehicle,plate_no,' . $id,
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
            Log::error('Collection management vehicle update failed: ' . $e->getMessage());

            return $this->sendError('Vehicle update failed: ' . $e->getMessage());
        }
    }

    private function transformListRow(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'plate_no' => $vehicle->plate_no,
            'body_type' => $vehicle->bodyType ? $vehicle->bodyType->name : null,
            'registration_date' => $vehicle->created_at,
            'status' => (int) $vehicle->status,
            'status_label' => ((int) $vehicle->status) === 1 ? 'ACTIVE' : 'INACTIVE',
            'exemption' => (int) $vehicle->exempted,
            'exemption_label' => ((int) $vehicle->exempted) === 1 ? 'EXEMPTED' : 'NOT EXEMPTED',
        ];
    }
}
