<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bank';
    protected $primaryKey = 'bank_id';

    public $timestamps = false;

    protected $fillable = [
        'bank_name',
        'short_name',
        'sort_code',
        'bank_code',
        'bi_code',
        'is_active',
        'erp_bc',
        'erp_br',
        'citi_code',
        'swift_code',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'bank_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    /**
     * Scope to get only active banks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive banks
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}

