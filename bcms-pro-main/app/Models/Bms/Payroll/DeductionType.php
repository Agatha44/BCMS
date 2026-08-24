<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'deduction_type';

    protected $primaryKey = 'deduction_type_id';

    public $timestamps = false;

    protected $fillable = [
        'deduction_name',
        'deduction_code',
        'calculation_type',
        'calculation_value',
        'is_before_tax',
        'employee_contribution_percentage',
        'employer_contribution_percentage',
        'priority',
        'is_active',
        'is_mandatory',
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
        'employee_contribution_percentage' => 'decimal:2',
        'employer_contribution_percentage' => 'decimal:2',
        'is_before_tax' => 'boolean',
        'is_active' => 'boolean',
        'is_mandatory' => 'boolean',
        'priority' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}
