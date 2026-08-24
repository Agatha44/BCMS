<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Models\PosTerminal;
use App\Models\Lane;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PosTerminalController extends BasicController
{
    /**
     * Back-office: register a POS terminal manually.
     */
    public function registerTerminal(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'lane_id' => 'required|integer|exists:lane,id',
            'mac_address' => 'required|string|max:17|unique:pos_terminals,mac_address',
            'ip_address' => 'required|ip',
            'terminal_type' => 'nullable|string|in:POS,Kiosk,Mobile',
            'location' => 'nullable|string|max:255',
            'configuration' => 'nullable|array',
            'firmware_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $lane = Lane::find($request->lane_id);
            if (!$lane) {
                return $this->sendError('Lane not found', ['lane_id' => $request->lane_id]);
            }

            if ($conflict = $this->activeTerminalOnLane($lane->id)) {
                return $this->sendError('Lane already has an active terminal', [
                    'lane_number' => $lane->lane_no,
                    'existing_terminal' => $conflict->name,
                    'existing_mac_address' => $conflict->mac_address,
                ]);
            }

            $terminal = new PosTerminal();
            $terminal->name = $request->name;
            $terminal->lane_id = $lane->id;
            $terminal->lane_number = $lane->lane_no;
            $terminal->mac_address = strtoupper(trim($request->mac_address));
            $terminal->ip_address = $request->ip_address;
            $terminal->terminal_type = $request->terminal_type ?? PosTerminal::TYPE_POS;
            $terminal->location = $request->location;
            $terminal->status = PosTerminal::STATUS_ACTIVE;
            $terminal->configuration = $request->configuration ?? [];
            $terminal->firmware_version = $request->firmware_version;
            $terminal->registered_by = auth()->id();
            $terminal->save();

            $apiKey = $terminal->generateApiKey();

            DB::commit();

            return $this->sendResponse([
                'terminal' => $terminal->fresh(),
                'api_key' => $apiKey,
            ], 'POS terminal registered successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to register POS terminal: ' . $e->getMessage());
        }
    }

    /**
     * Back-office: list POS terminals.
     */
    public function listTerminals(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) ($request->per_page ?? 15), 100);
            $query = PosTerminal::with(['lane:id,lane_no', 'registeredBy:id,username']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('lane_number')) {
                $query->forLane($request->lane_number);
            }

            if ($request->filled('terminal_type')) {
                $query->where('terminal_type', $request->terminal_type);
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('lane_number', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            }

            $terminals = $query->orderByDesc('created_at')->paginate($perPage);

            return $this->sendResponse([
                'terminals' => $terminals->items(),
                'pagination' => [
                    'current_page' => $terminals->currentPage(),
                    'last_page' => $terminals->lastPage(),
                    'per_page' => $terminals->perPage(),
                    'total' => $terminals->total(),
                ],
            ], 'POS terminals retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve POS terminals: ' . $e->getMessage());
        }
    }

    /**
     * Back-office: get one terminal.
     */
    public function getTerminal($id): JsonResponse
    {
        try {
            $terminal = PosTerminal::with([
                'lane:id,lane_no,camera_ip,reader_ip,status',
                'registeredBy:id,username',
            ])->find($id);

            if (!$terminal) {
                return $this->sendError('POS terminal not found');
            }

            return $this->sendResponse([
                'terminal' => $terminal,
            ], 'POS terminal retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve POS terminal: ' . $e->getMessage());
        }
    }

    /**
     * Back-office: update terminal name, lane, location, type.
     */
    public function updateTerminal(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'lane_id' => 'nullable|integer|exists:lane,id',
            'location' => 'nullable|string|max:255',
            'terminal_type' => 'nullable|string|in:POS,Kiosk,Mobile',
            'firmware_version' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:pending,active,inactive,maintenance',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $terminal = PosTerminal::find($id);
            if (!$terminal) {
                return $this->sendError('POS terminal not found');
            }

            if ($request->filled('name')) {
                $terminal->name = $request->name;
            }

            if ($request->filled('location')) {
                $terminal->location = $request->location;
            }

            if ($request->filled('terminal_type')) {
                $terminal->terminal_type = $request->terminal_type;
            }

            if ($request->filled('firmware_version')) {
                $terminal->firmware_version = $request->firmware_version;
            }

            if ($request->filled('lane_id')) {
                $lane = Lane::find($request->lane_id);
                if (!$lane) {
                    return $this->sendError('Lane not found', ['lane_id' => $request->lane_id]);
                }

                if ($conflict = $this->activeTerminalOnLane($lane->id, $terminal->id)) {
                    return $this->sendError('Lane already has an active terminal', [
                        'lane_number' => $lane->lane_no,
                        'existing_terminal' => $conflict->name,
                        'existing_mac_address' => $conflict->mac_address,
                    ]);
                }

                $terminal->lane_id = $lane->id;
                $terminal->lane_number = $lane->lane_no;
                $terminal->location = $terminal->location ?: ('Lane ' . $lane->lane_no);

                if (!$request->filled('status')) {
                    $terminal->status = PosTerminal::STATUS_ACTIVE;
                }
            }

            if ($request->filled('status')) {
                $terminal->status = $request->status;
            }

            $terminal->save();

            DB::commit();

            return $this->sendResponse([
                'terminal' => $terminal->fresh(['lane:id,lane_no']),
            ], 'POS terminal updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to update POS terminal: ' . $e->getMessage());
        }
    }

    /**
     * Back-office: update terminal status only.
     */
    public function updateTerminalStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,active,inactive,maintenance',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            $terminal = PosTerminal::find($id);
            if (!$terminal) {
                return $this->sendError('POS terminal not found');
            }

            if ($request->status === PosTerminal::STATUS_ACTIVE && !$terminal->isLaneConfigured()) {
                if (!$terminal->lane_id) {
                    return $this->sendError('Assign a lane before activating this terminal');
                }
            }

            if ($request->status === PosTerminal::STATUS_ACTIVE && $terminal->lane_id) {
                if ($conflict = $this->activeTerminalOnLane($terminal->lane_id, $terminal->id)) {
                    return $this->sendError('Lane already has an active terminal', [
                        'lane_number' => $terminal->lane_number,
                        'existing_terminal' => $conflict->name,
                    ]);
                }
            }

            $terminal->status = $request->status;
            $terminal->save();

            return $this->sendResponse([
                'terminal' => $terminal->fresh(['lane:id,lane_no']),
            ], 'POS terminal status updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update POS terminal status: ' . $e->getMessage());
        }
    }

    /**
     * Roadside POS self-registration on boot (MAC only — lane assigned from back office).
     */
    public function selfRegister(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mac_address' => 'required|string|max:17',
            'ip_address' => 'nullable|ip',
            'terminal_type' => 'nullable|string|in:POS,Kiosk,Mobile',
            'device_info' => 'nullable|string|max:255',
            'firmware_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $macAddress = strtoupper(trim((string) $request->mac_address));
            $ipAddress = $request->ip_address ?? $request->ip() ?? '0.0.0.0';
            $terminal = PosTerminal::where('mac_address', $macAddress)->first();

            if ($terminal) {
                $terminal->ip_address = $ipAddress;
                if ($request->filled('device_info')) {
                    $config = $terminal->configuration ?? [];
                    $config['device_info'] = $request->device_info;
                    $terminal->configuration = $config;
                }
                if ($request->filled('firmware_version')) {
                    $terminal->firmware_version = $request->firmware_version;
                }
                $terminal->save();
                $terminal->updateHeartbeat();

                DB::commit();

                return $this->sendResponse(
                    $this->deviceRegistrationPayload($terminal, false),
                    $terminal->isLaneConfigured()
                        ? 'Device registered'
                        : 'Lane configuration pending'
                );
            }

            $terminal = new PosTerminal();
            $terminal->name = 'POS ' . substr(str_replace(':', '', $macAddress), -8);
            $terminal->mac_address = $macAddress;
            $terminal->ip_address = $ipAddress;
            $terminal->lane_id = null;
            $terminal->lane_number = null;
            $terminal->terminal_type = $request->terminal_type ?? PosTerminal::TYPE_POS;
            $terminal->location = null;
            $terminal->status = PosTerminal::STATUS_PENDING;
            $terminal->configuration = $request->filled('device_info')
                ? ['device_info' => $request->device_info]
                : [];
            $terminal->firmware_version = $request->firmware_version;
            $terminal->registered_by = null;
            $terminal->save();
            $terminal->generateApiKey();
            $terminal->updateHeartbeat();

            DB::commit();

            Log::info('POS device self-registered (pending lane)', [
                'mac_address' => $macAddress,
                'terminal_id' => $terminal->id,
            ]);

            return $this->sendResponse(
                $this->deviceRegistrationPayload($terminal, true),
                'Device registered — lane configuration pending'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('POS device self-registration failed', [
                'error' => $e->getMessage(),
                'mac_address' => $request->mac_address ?? null,
            ]);

            return $this->sendError('Failed to register device: ' . $e->getMessage());
        }
    }

    /**
     * Roadside POS polls device configuration by MAC.
     */
    public function deviceStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mac_address' => 'required|string|max:17',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        $macAddress = strtoupper(trim((string) $request->mac_address));
        $terminal = PosTerminal::where('mac_address', $macAddress)->first();

        if (!$terminal) {
            return $this->sendError('Device not registered', [
                'mac_address' => $macAddress,
            ]);
        }

        $terminal->updateHeartbeat();

        return $this->sendResponse(
            $this->deviceRegistrationPayload($terminal, false),
            $terminal->isLaneConfigured()
                ? 'Device ready'
                : 'Lane configuration pending'
        );
    }

    /**
     * Terminal heartbeat endpoint.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mac_address' => 'required|string|max:17',
            'ip_address' => 'nullable|ip',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors()->toArray());
        }

        try {
            $macAddress = strtoupper(trim((string) $request->mac_address));
            $terminal = PosTerminal::where('mac_address', $macAddress)->first();

            if (!$terminal) {
                return $this->sendError('Terminal not registered', [
                    'mac_address' => $macAddress,
                ]);
            }

            $terminal->updateHeartbeat();
            if ($request->filled('ip_address')) {
                $terminal->ip_address = $request->ip_address;
            }
            $terminal->save();

            return $this->sendResponse(
                $this->deviceRegistrationPayload($terminal, false),
                'Heartbeat received successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to process heartbeat: ' . $e->getMessage());
        }
    }

    private function activeTerminalOnLane(int $laneId, ?int $excludeTerminalId = null): ?PosTerminal
    {
        $query = PosTerminal::where('lane_id', $laneId)
            ->where('status', PosTerminal::STATUS_ACTIVE);

        if ($excludeTerminalId) {
            $query->where('id', '!=', $excludeTerminalId);
        }

        return $query->first();
    }

    private function deviceRegistrationPayload(PosTerminal $terminal, bool $registered): array
    {
        return [
            'terminal' => [
                'id' => $terminal->id,
                'name' => $terminal->name,
                'mac_address' => $terminal->mac_address,
                'ip_address' => $terminal->ip_address,
                'lane_id' => $terminal->lane_id,
                'lane_number' => $terminal->lane_number,
                'status' => $terminal->status,
                'terminal_type' => $terminal->terminal_type,
                'location' => $terminal->location,
                'last_heartbeat' => $terminal->last_heartbeat,
            ],
            'registered' => $registered,
            'lane_configured' => $terminal->isLaneConfigured(),
        ];
    }
}
