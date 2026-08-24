<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceListAudit extends Model
{
    use HasFactory;
    
    protected $table = 'price_list_audit';
    
    // Action constants
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    
    protected $fillable = [
        'price_list_id',
        'previous_body_type_id',
        'new_body_type_id',
        'previous_amount',
        'new_amount',
        'previous_daily_bundle_amount',
        'new_daily_bundle_amount',
        'previous_weekly_bundle_amount',
        'new_weekly_bundle_amount',
        'previous_monthly_bundle_amount',
        'new_monthly_bundle_amount',
        'previous_status',
        'new_status',
        'action',
        'updated_by'
    ];
    
    protected $casts = [
        'previous_amount' => 'float',
        'new_amount' => 'float',
        'previous_daily_bundle_amount' => 'float',
        'new_daily_bundle_amount' => 'float',
        'previous_weekly_bundle_amount' => 'float',
        'new_weekly_bundle_amount' => 'float',
        'previous_monthly_bundle_amount' => 'float',
        'new_monthly_bundle_amount' => 'float',
        'previous_status' => 'boolean',
        'new_status' => 'boolean',
        'previous_body_type_id' => 'integer',
        'new_body_type_id' => 'integer',
        'updated_by' => 'integer'
    ];
    
    /**
     * Get the price list that this audit belongs to
     */
    public function priceList()
    {
        return $this->belongsTo(PriceList::class, 'price_list_id', 'id');
    }
    
    /**
     * Get the user who made the change
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
    
    /**
     * Scope for specific actions
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }
    
    /**
     * Scope for specific price list
     */
    public function scopeForPriceList($query, $priceListId)
    {
        return $query->where('price_list_id', $priceListId);
    }
}
