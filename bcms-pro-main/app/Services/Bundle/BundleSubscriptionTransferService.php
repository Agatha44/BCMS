<?php

namespace App\Services\Bundle;

use App\Models\BundleSubscription;
use App\Models\Vehicle;
use App\Services\Audit\OwenItAuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BundleSubscriptionTransferService
{
    private const AUDIT_TAG = 'bundle_subscription_transfer';

    /**
     * Load bundle subscription details for edit/transfer UI (legacy edit-bundle).
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function getSubscriptionForEdit(int $subscriptionId): array
    {
        $row = DB::table('bundle_subscriptions as bs')
            ->leftJoin('account as a', 'bs.account_id', '=', 'a.account_no')
            ->leftJoin('vehicle as v', 'v.id', '=', 'bs.vehicle_id')
            ->leftJoin('toll_bundles as tb', 'tb.id', '=', 'bs.bundle_id')
            ->where('bs.id', $subscriptionId)
            ->select([
                'bs.*',
                DB::raw("CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.surname, '')) as customer"),
                'a.account_no',
                'bs.status as bundle_status',
                'v.plate_no',
                'tb.bundle_description',
            ])
            ->first();

        if ($row === null) {
            throw new \RuntimeException('Bundle subscription not found');
        }

        return (array) $row;
    }

    /**
     * Transfer an active bundle subscription to a different vehicle (by plate number).
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function transferToVehicle(int $subscriptionId, string $newPlateNo, int $updatedBy, string $reason): array
    {
        $newPlateNo = strtoupper(trim($newPlateNo));
        $reason = trim($reason);

        if ($newPlateNo === '') {
            throw new \RuntimeException('New plate number is required');
        }

        if ($reason === '') {
            throw new \RuntimeException('Reason is required');
        }

        DB::beginTransaction();

        try {
            $subscription = BundleSubscription::with(['vehicle', 'tollBundle'])->find($subscriptionId);

            if ($subscription === null) {
                throw new \RuntimeException('Bundle subscription not found');
            }

            $targetVehicle = Vehicle::where('plate_no', $newPlateNo)->first();

            if ($targetVehicle === null) {
                throw new \RuntimeException('Vehicle not found for plate number: ' . $newPlateNo);
            }

            $oldVehicleId = (int) $subscription->vehicle_id;
            $newVehicleId = (int) $targetVehicle->id;

            if ($oldVehicleId === $newVehicleId) {
                throw new \RuntimeException('Bundle is already assigned to this vehicle');
            }

            $oldPlateNo = $subscription->vehicle?->plate_no;
            $auditKeys = ['vehicle_id', 'updated_by', 'reason'];
            $oldAudit = $subscription->only($auditKeys);
            $oldAudit['plate_no'] = $oldPlateNo;
            $oldAudit['bundle_subscription_id'] = $subscription->id;
            $oldAudit['bundle_id'] = $subscription->bundle_id;
            $oldAudit['account_id'] = $subscription->account_id;
            $oldAudit['start_date'] = $subscription->start_date;
            $oldAudit['expire_date'] = $subscription->expire_date;
            $oldAudit['status'] = $subscription->status;

            $subscription->vehicle_id = $newVehicleId;
            $subscription->updated_by = $updatedBy;
            $subscription->reason = $reason;

            if (!$subscription->save()) {
                throw new \RuntimeException('Failed to update bundle subscription');
            }

            $subscription->refresh()->load(['vehicle', 'tollBundle']);

            $newAudit = $subscription->only($auditKeys);
            $newAudit['plate_no'] = $subscription->vehicle?->plate_no;
            $newAudit['bundle_subscription_id'] = $subscription->id;
            $newAudit['bundle_id'] = $subscription->bundle_id;
            $newAudit['account_id'] = $subscription->account_id;
            $newAudit['start_date'] = $subscription->start_date;
            $newAudit['expire_date'] = $subscription->expire_date;
            $newAudit['status'] = $subscription->status;
            $newAudit['old_plate_no'] = $oldPlateNo;
            $newAudit['new_plate_no'] = $subscription->vehicle?->plate_no;
            $newAudit['bundle_description'] = $subscription->tollBundle?->bundle_description;

            $this->persistTransferAudit((int) $subscription->id, $oldAudit, $newAudit);

            DB::commit();

            return [
                'subscription_id' => (int) $subscription->id,
                'old_vehicle_id' => $oldVehicleId,
                'new_vehicle_id' => $newVehicleId,
                'old_plate_no' => $oldPlateNo,
                'new_plate_no' => $subscription->vehicle?->plate_no,
                'updated_by' => $updatedBy,
                'reason' => $reason,
                'bundle_id' => $subscription->bundle_id,
                'expire_date' => $subscription->expire_date,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BundleSubscriptionTransferService: transfer failed', [
                'subscription_id' => $subscriptionId,
                'new_plate_no' => $newPlateNo,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function persistTransferAudit(int $subscriptionId, array $oldValues, array $newValues): void
    {
        $writer = app(OwenItAuditWriter::class);
        if (!$writer->isEnabled()) {
            return;
        }

        $writer->record(
            'updated',
            BundleSubscription::class,
            $subscriptionId,
            $oldValues,
            $newValues,
            self::AUDIT_TAG
        );
    }
}
