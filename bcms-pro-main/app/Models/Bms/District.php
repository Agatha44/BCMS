<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_district';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'domicile_id',
        'description',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'domicile_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    /**
     * Scope to get only active districts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive districts
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get the domicile (region) for this district
     */
    public function domicile()
    {
        return $this->belongsTo(Domicile::class, 'domicile_id');
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}

