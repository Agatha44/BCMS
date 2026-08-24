<?php

namespace App\Services\TollCapture;

use App\Models\Lane;
use App\Models\PriceList;
use App\Models\TollCapture;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Toll booth capture persistence (mirrors {@see \App\Services\Vehicle\VehicleUpdateService} for images).
 */
class TollCaptureStoreService
{
    public function __construct(
        private TollCaptureImageStorageService $imageStorage
    ) {
    }

    /**
     * Register a pending capture at a lane. Optional image via multipart file or base64 (same as vehicle update).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function register(
        array $data,
        ?UploadedFile $captureImage = null,
        ?string $captureImageBase64 = null,
        bool $clearCaptureImage = false
    ): TollCapture {
        $plateNo = strtoupper(trim((string) $data['plate_no']));
        [$laneId, $laneNumber] = $this->resolveLane($data);
        [$bodyTypeId, $vehicleId, $amount] = $this->resolvePricing($plateNo, $data);

        $imagePath = null;
        if ($clearCaptureImage) {
            $imagePath = null;
        } elseif ($captureImage !== null && $captureImage->isValid()) {
            $imagePath = $this->imageStorage->storeFromUploadedFile($plateNo, $laneNumber, $captureImage);
        } elseif ($captureImageBase64 !== null && trim($captureImageBase64) !== '') {
            $imagePath = $this->imageStorage->storeFromBase64Payload($plateNo, $laneNumber, $captureImageBase64);
        }

        $now = now()->toDateTimeString();

        DB::beginTransaction();

        try {
            $this->clearPendingCapturesOnLane($laneNumber, $laneId);

            $capture = TollCapture::create([
                'plate_no' => $plateNo,
                'lane_id' => $laneId,
                'lane_number' => $laneNumber,
                'body_type_id' => $bodyTypeId,
                'vehicle_id' => $vehicleId,
                'amount' => $amount,
                'image' => $imagePath,
                'status' => TollCapture::STATUS_PENDING,
                'shift_id' => $data['shift_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::commit();

            return $capture;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($imagePath !== null) {
                $this->imageStorage->deleteStoredFileIfManaged($imagePath);
            }
            Log::error('TollCaptureStoreService::register ' . $e->getMessage(), [
                'plate_no' => $plateNo,
                'lane_number' => $laneNumber,
            ]);

            throw $e;
        }
    }

    /**
     * Update image on a pending capture (mirrors vehicle image update via {@see VehicleUpdateService}).
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function updateImage(
        TollCapture $capture,
        ?UploadedFile $captureImage = null,
        ?string $captureImageBase64 = null,
        bool $remove = false
    ): TollCapture {
        if ($remove) {
            $this->imageStorage->deleteStoredFileIfManaged($capture->image);
            $capture->image = null;
            $capture->save();

            return $capture->fresh();
        }

        if ($captureImage !== null && $captureImage->isValid()) {
            $this->imageStorage->deleteStoredFileIfManaged($capture->image);
            $capture->image = $this->imageStorage->storeFromUploadedFile(
                $capture->plate_no,
                $capture->lane_number,
                $captureImage
            );
            $capture->save();

            return $capture->fresh();
        }

        if ($captureImageBase64 !== null && trim($captureImageBase64) !== '') {
            $this->imageStorage->deleteStoredFileIfManaged($capture->image);
            $capture->image = $this->imageStorage->storeFromBase64Payload(
                $capture->plate_no,
                $capture->lane_number,
                $captureImageBase64
            );
            $capture->save();

            return $capture->fresh();
        }

        throw new \InvalidArgumentException('No image payload provided');
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveLane(array $data): array
    {
        $laneNumber = $data['lane_number'] ?? null;
        $laneId = $data['lane_id'] ?? null;

        if (! $laneNumber && $laneId) {
            $laneNumber = Lane::find($laneId)?->lane_no;
        }

        if (! $laneId && $laneNumber) {
            $laneId = Lane::where('lane_no', $laneNumber)->value('id');
        }

        return [$laneId, $laneNumber];
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: float|int|null}
     *
     * @throws \InvalidArgumentException
     */
    private function resolvePricing(string $plateNo, array $data): array
    {
        $vehicle = Vehicle::where('plate_no', $plateNo)->first();
        $bodyTypeId = $data['body_type_id'] ?? $vehicle?->body_type_id;
        $vehicleId = $vehicle?->id;
        $amount = $data['amount'] ?? null;

        if ($amount === null && $bodyTypeId) {
            $amount = PriceList::where('body_type_id', $bodyTypeId)
                ->where('status', PriceList::STATUS_ACTIVE)
                ->value('amount');
        }

        if ($amount === null) {
            throw new \InvalidArgumentException('Unable to determine toll amount');
        }

        return [$bodyTypeId, $vehicleId, $amount];
    }

    private function clearPendingCapturesOnLane(?string $laneNumber, ?int $laneId): void
    {
        $query = TollCapture::pending();
        if ($laneNumber) {
            $query->where('lane_number', $laneNumber);
        } elseif ($laneId) {
            $query->where('lane_id', $laneId);
        }

        foreach ($query->get() as $existing) {
            $this->imageStorage->deleteStoredFileIfManaged($existing->image);
        }

        $query->delete();
    }
}
