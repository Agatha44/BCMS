<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArrearsReason extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'arrears_reasons';

    protected $primaryKey = 'arrears_reason_id';

    public $timestamps = false;

    protected $fillable = [
        'reason_name',
        'reason_code',
        'is_taxable',
        'is_active',
        'start_date',
        'end_date',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}

