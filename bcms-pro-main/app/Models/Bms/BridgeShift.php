<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BridgeShift extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_shifts';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'shift_name',
        'start_time',
        'end_time',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}


