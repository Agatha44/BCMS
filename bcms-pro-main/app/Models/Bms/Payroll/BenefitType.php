<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BenefitType extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'benefit_type';
    
    protected $primaryKey = 'benefit_type_id';

    public $timestamps = false;

    protected $fillable = [
        'benefit_name',
        'benefit_code',
        'calculation_type',
        'calculation_value',
        'is_taxable',
        'is_active',
        'contract_type',
        'department_section',
        'job_title_position',
        'start_date',
        'end_date',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'calculation_value' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}

