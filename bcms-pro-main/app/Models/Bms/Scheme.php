<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scheme extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'scheme';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'scheme_name',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    /**
     * Scope to get schemes ordered by name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('scheme_name', 'asc');
    }
}

