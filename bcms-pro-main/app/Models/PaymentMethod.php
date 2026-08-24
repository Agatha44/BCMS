<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;
    
    protected $table = 'payment_methods';
    
    protected $fillable = [
        'id',
        'description',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * Get all lanes that use this payment method
     */
    public function lanes()
    {
        return $this->hasMany(Lane::class, 'payment_method', 'id');
    }

    /**
     * Scope to get only active payment methods
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('description');
    }
}
