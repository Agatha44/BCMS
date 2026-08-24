<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BridgeEmploymentType extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_employment_type';
    protected $primaryKey = 'emptype_id';

    public $timestamps = false;

    public $incrementing = true;

    protected $fillable = [
        'emptype_name',
        'prob_months',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'emptype_id' => 'integer',
        'prob_months' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}

