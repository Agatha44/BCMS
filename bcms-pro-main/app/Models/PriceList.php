<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\BodyType;

class PriceList extends Model
{
    use HasFactory;
    
    protected $table = 'price_list';
    const STATUS_ACTIVE = '1';
    const STATUS_INACTIVE = '0';

    protected $fillable = [
        'body_type_id',
        'amount',
        'daily_bundle_amount',
        'weekly_bundle_amount',
        'monthly_bundle_amount',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'amount' => 'float',
        'daily_bundle_amount' => 'float',
        'weekly_bundle_amount' => 'float',
        'monthly_bundle_amount' => 'float',
        'status' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer'
    ];

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class, 'body_type_id', 'id');
    }

    /**
     * Get the user who created this price
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this price
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get only active prices
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get only inactive prices
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Get the price amount based on bundle type
     * 
     * @param int|null $bundle_id Bundle type ID (1=daily, 2=weekly, 3=monthly)
     * @return float Returns bundle amount if bundle_id provided, otherwise returns regular toll fee amount
     */
    public function getAmount($bundle_id = null)
    {
        if (!is_null($bundle_id)) {
            if ($bundle_id == 1) {
                return $this->daily_bundle_amount;
            } elseif ($bundle_id == 2) {
                return $this->weekly_bundle_amount;
            } elseif ($bundle_id == 3) {
                return $this->monthly_bundle_amount;
            }
        }
        return $this->amount;
    }
}