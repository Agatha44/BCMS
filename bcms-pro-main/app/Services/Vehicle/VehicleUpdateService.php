<?php

namespace App\Services\Vehicle;

use App\Models\AccountVehicle;
use App\Models\Vehicle;
use App\Services\Audit\OwenItAuditWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleUpdateService
{
    /**
     * Same business rules as VehicleController::updateVehicle().
     * Persists vehicle + account_vehicle link. When the `audits` table exists and
     * `AUDITING_ENABLED` is not false, writes OwenIt-compatible audit rows (works without the owen-it package).
     *
     * @param  array<string, mixed>  $data  validated keys: vehicle_id, plate_no, body_type_id, optional card_number, rfid_tag_no; optional account_no (ignored unless re-linking); account_no + account_vehicle_id when re-linking to an account
     * @param  \Illuminate\Http\UploadedFile|null  $vehicleImage  optional multipart file (jpeg, png, gif, webp)
     * @param  string|null  $vehicleImageBase64  optional data URI or raw base64 payload
     * @param  bool  $clearVehicleImage  when true, clears `vehicle.image` and removes the previous file if it lives under the configured image directory
     * @return array{vehicle: Vehicle, account_vehicle: AccountVehicle|null}
     *
     * @throws \Throwable
     */
    public function updateWithAccountVehicle(
        array $data,
        ?UploadedFile $vehicleImage = null,
        ?string $vehicleImageBase64 = null,
        bool $clearVehicleImage = false
    ): array {
        DB::beginTransaction();

        try {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            $vehicleAuditKeys = ['plate_no', 'rfid_tag_no', 'body_type_id', 'card_number', 'card_number_status', 'rfid_tag_status', 'image'];
            $oldVehicle = $vehicle->only($vehicleAuditKeys);

            if (!empty($data['card_number'])) {
                $vehicle->rfid_tag_status = 0;
                $vehicle->card_number = $data['card_number'];
                $vehicle->card_number_status = 1;
            }

            $vehicle->plate_no = $data['plate_no'];
            $vehicle->rfid_tag_no = $data['rfid_tag_no'] ?? null;
            $vehicle->body_type_id = $data['body_type_id'];

            $imageStorage = app(VehicleImageStorageService::class);
            if ($clearVehicleImage) {
                $imageStorage->deleteStoredFileIfManaged($vehicle->image);
                $vehicle->image = null;
            } elseif ($vehicleImage !== null && $vehicleImage->isValid()) {
                $imageStorage->deleteStoredFileIfManaged($vehicle->image);
                $vehicle->image = $imageStorage->storeUploadedFile((int) $vehicle->id, $vehicleImage);
            } elseif ($vehicleImageBase64 !== null && trim($vehicleImageBase64) !== '') {
                $imageStorage->deleteStoredFileIfManaged($vehicle->image);
                $vehicle->image = $imageStorage->storeFromBase64Payload((int) $vehicle->id, $vehicleImageBase64);
            }

            if (!$vehicle->save()) {
                DB::rollBack();
                throw new \RuntimeException('Failed to update vehicle');
            }

            $vehicle->refresh();

            $accountVehicleFresh = null;
            $oldAccountVehicle = null;
            $newAccountVehicle = null;
            $accountVehicleIdForAudit = null;

            if ($this->accountLinkUpdateRequested($data)) {
                $accountVehicle = AccountVehicle::findOrFail($data['account_vehicle_id']);
                $avKeys = ['account_id', 'vehicle_id'];
                $oldAccountVehicle = $accountVehicle->only($avKeys);

                $accountVehicle->account_id = $data['account_no'];
                $accountVehicle->vehicle_id = $vehicle->id;

                if (!$accountVehicle->save()) {
                    DB::rollBack();
                    throw new \RuntimeException('Failed to update account vehicle link');
                }

                $accountVehicle->refresh();
                $accountVehicleFresh = $accountVehicle->fresh();
                $newAccountVehicle = $accountVehicleFresh->only($avKeys);
                $accountVehicleIdForAudit = (int) $accountVehicleFresh->id;
            }

            $this->persistAuditsIfEnabled(
                Vehicle::class,
                (int) $vehicle->id,
                $oldVehicle,
                $vehicle->only($vehicleAuditKeys),
                $accountVehicleIdForAudit !== null ? AccountVehicle::class : null,
                $accountVehicleIdForAudit,
                $oldAccountVehicle,
                $newAccountVehicle
            );

            DB::commit();

            return [
                'vehicle' => $vehicle->fresh(),
                'account_vehicle' => $accountVehicleFresh,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('VehicleUpdateService: ' . $e->getMessage());

            throw $e;
        }
    }

    private function accountLinkUpdateRequested(array $data): bool
    {
        if (!array_key_exists('account_vehicle_id', $data) || $data['account_vehicle_id'] === null) {
            return false;
        }

        return trim((string) $data['account_vehicle_id']) !== '';
    }

    /**
     * @param  array<string, mixed>  $oldVehicle
     * @param  array<string, mixed>  $newVehicle
     * @param  array<string, mixed>|null  $oldAccountVehicle
     * @param  array<string, mixed>|null  $newAccountVehicle
     */
    private function persistAuditsIfEnabled(
        string $vehicleType,
        int $vehicleId,
        array $oldVehicle,
        array $newVehicle,
        ?string $accountVehicleType,
        ?int $accountVehicleId,
        ?array $oldAccountVehicle,
        ?array $newAccountVehicle
    ): void {
        $writer = app(OwenItAuditWriter::class);
        if (!$writer->isEnabled()) {
            return;
        }

        if (json_encode($oldVehicle) !== json_encode($newVehicle)) {
            $writer->record('updated', $vehicleType, $vehicleId, $oldVehicle, $newVehicle, 'VehicleUpdateService');
        }

        if (
            $accountVehicleType !== null
            && $accountVehicleId !== null
            && is_array($oldAccountVehicle)
            && is_array($newAccountVehicle)
            && json_encode($oldAccountVehicle) !== json_encode($newAccountVehicle)
        ) {
            $writer->record('updated', $accountVehicleType, $accountVehicleId, $oldAccountVehicle, $newAccountVehicle, 'VehicleUpdateService');
        }
    }
}
