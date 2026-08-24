<?php

namespace App\Console\Commands;

use App\Models\Lane;
use App\Models\PriceList;
use App\Models\TollCapture;
use App\Models\Vehicle;
use App\Services\TollCapture\TollCaptureImageStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedTollCaptureTestData extends Command
{
    protected $signature = 'toll-capture:seed-test
                            {--lane-number=1 : Lane number the POS is configured for}
                            {--lane-id= : Optional lane ID (resolved from lane-number if omitted)}
                            {--plate= : Plate number (uses first registered vehicle if omitted)}
                            {--amount= : Toll amount override}
                            {--image= : Optional ANPR-style base64 image for testing}
                            {--clear : Remove all pending captures before seeding}';

    protected $description = 'Insert dummy toll capture data for POS testing without ANPR';

    public function handle(): int
    {
        if (! Schema::hasTable('toll_capture')) {
            $this->error('toll_capture table not found. Run: php artisan migrate --path=database/migrations/toll_capture');

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $deleted = TollCapture::pending()->delete();
            $this->info("Cleared {$deleted} pending capture(s).");
        }

        $laneNumber = (string) $this->option('lane-number');
        $laneId = $this->option('lane-id');

        if (! $laneId) {
            $lane = Lane::where('lane_no', $laneNumber)->first();
            $laneId = $lane?->id;
        }

        $plateNo = $this->option('plate');
        $vehicle = null;

        if ($plateNo) {
            $plateNo = strtoupper(trim($plateNo));
            $vehicle = Vehicle::where('plate_no', $plateNo)->first();
        } else {
            $vehicle = Vehicle::orderBy('id')->first();
            $plateNo = $vehicle?->plate_no ?? 'TEST001';
        }

        $bodyTypeId = $vehicle?->body_type_id;
        $vehicleId = $vehicle?->id;
        $amount = $this->option('amount');

        if ($amount === null && $bodyTypeId) {
            $price = PriceList::where('body_type_id', $bodyTypeId)
                ->where('status', PriceList::STATUS_ACTIVE)
                ->first();
            $amount = $price?->amount;
        }

        $amount = $amount ?? 1500;

        $imagePath = null;
        if ($this->option('image')) {
            try {
                $imagePath = app(TollCaptureImageStorageService::class)->storeFromBase64Payload(
                    $plateNo,
                    $laneNumber,
                    $this->option('image')
                );
            } catch (\Throwable $e) {
                $this->warn('Could not save test image: ' . $e->getMessage());
            }
        }

        $now = now()->toDateTimeString();

        DB::beginTransaction();
        try {
            TollCapture::pending()
                ->where('lane_number', $laneNumber)
                ->delete();

            $capture = TollCapture::create([
                'plate_no' => $plateNo,
                'lane_id' => $laneId,
                'lane_number' => $laneNumber,
                'body_type_id' => $bodyTypeId,
                'vehicle_id' => $vehicleId,
                'amount' => $amount,
                'image' => $imagePath,
                'status' => TollCapture::STATUS_PENDING,
                'shift_id' => 1,
                'user_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::commit();

            $this->info('Dummy toll capture created successfully.');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $capture->id],
                    ['Plate', $capture->plate_no],
                    ['Lane', $capture->lane_number],
                    ['Lane ID', $capture->lane_id ?? '—'],
                    ['Body Type ID', $capture->body_type_id ?? '—'],
                    ['Amount (TZS)', $capture->amount],
                    ['Status', $capture->status],
                    ['Vehicle in DB', $vehicle ? 'Yes' : 'No (dummy plate)'],
                    ['Image path', $imagePath ?? 'None (ANPR sends base64 on live capture)'],
                ]
            );

            $this->newLine();
            $this->line('POS should poll: GET /api/pos/toll-capture?lane_number=' . $laneNumber . '&mac_address=<your-mac>');
            $this->line('Or register via API: POST /api/toll-capture');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to seed toll capture: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
