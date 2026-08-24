<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EmployeeArrears extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'employee_arrears';

    protected $primaryKey = 'employee_arrears_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_national_id',
        'arrears_reason_id',
        'payroll_month',
        'payroll_year',
        'payroll_number',
        'arrears_amount',
        'gross_amount',
        'net_amount',
        'taxable_amount',
        'tax_free_amount',
        'overtime_days',
        'overtime_rate',
        'overtime_gross_amount',
        'overtime_net_amount',
        'workflow_status',
        'payment_status',
        'arrears_date',
        'is_active',
        'notes',
        'created_by',
        'created_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'payroll_month' => 'integer',
        'payroll_year' => 'integer',
        'arrears_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_free_amount' => 'decimal:2',
        'overtime_days' => 'decimal:2',
        'overtime_rate' => 'decimal:4',
        'overtime_gross_amount' => 'decimal:2',
        'overtime_net_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'arrears_date' => 'date',
        'created_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }
}

