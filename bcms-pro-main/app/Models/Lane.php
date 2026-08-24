<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lane extends Model
{
    use HasFactory;
    
    protected $table = 'lane';
    
    protected $fillable = [
        'lane_no',
        'camera_ip',
        'reader_ip',
        'status',
        'created_by',
        'updated_by',
        'com_port',
        'payment_method',
        'reader_port',
        'mac_address',
        'gate_ip',
    ];

    protected $casts = [
        'status' => 'boolean',
        'payment_method' => 'integer',
        'reader_port' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Scope to get only active lanes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get only inactive lanes
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
     * Get the payment method for this lane
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method', 'id');
    }

    /**
     * Get payment method text attribute
     */
    public function getPaymentMethodTextAttribute(): string
    {
        return $this->paymentMethod?->description ?? 'Unknown';
    }

    /**
     * Get the user who created this lane
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this lane
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
