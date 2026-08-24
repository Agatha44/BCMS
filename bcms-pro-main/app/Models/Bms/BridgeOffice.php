<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BridgeOffice extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_office';

    public $timestamps = false;

    protected $fillable = [
        'office_name',
        'office_code',
        'auto_generate_pf',
        'description',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'auto_generate_pf' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    /**
     * Scope to get only active offices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get offices that auto-generate PF
     */
    public function scopeAutoGeneratePf($query)
    {
        return $query->where('auto_generate_pf', true);
    }

    /**
     * Scope to get offices that require manual PF entry
     */
    public function scopeManualPf($query)
    {
        return $query->where('auto_generate_pf', false);
    }
}

