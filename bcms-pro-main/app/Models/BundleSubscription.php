<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Vehicle;
use App\Models\TollBundle;
use App\Models\Account;

class BundleSubscription extends Model
{
    use HasFactory;
    protected $table = 'bundle_subscriptions';

    // status
    const STATUS_PENDING = '0';
    const STATUS_ACTIVE = '1';
    const STATUS_INACTIVE = '2';
    const STATUS_CANCELLED = '3';

    const ERMS_STATUS_PENDING = 0;
    const ERMS_STATUS_POSTED = 1;
    const ERMS_STATUS_FAILED = 2;

    protected $fillable = [
        'account_id',
        'vehicle_id',
        'bundle_id',
        'status',
        'start_date',
        'expire_date',
        'erms_status',
        'erms_submitted_at',
        'created_by',
        'updated_by',
        'reason',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_no');
    }

    public function tollBundle()
    {
        return $this->belongsTo(TollBundle::class, 'bundle_id', 'id');
    }

    public static function activeSubscription($account_id, $vehicle_id)
    {
        // get current active subscription of an account on a certain vehicle
        return BundleSubscription::query()
            ->where('account_id', $account_id)
            ->where('vehicle_id', $vehicle_id)
            ->where('status', self::STATUS_ACTIVE)
            ->where('expire_date', '>', now())
            ->orderBy('expire_date', 'desc')
            ->first();
    }

}
