<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'payroll_runs';

    public $timestamps = false;

    protected $fillable = [
        'payroll_month',
        'payroll_year',
        'payroll_number',
        'status',
        'prepared_by',
        'prepared_at',
        'initiated_by',
        'initiated_at',
        'verified_by',
        'verified_at',
        'examined_by',
        'examined_at',
        'approved_by',
        'approved_at',
        'erms_status',
        'erms_submitted_at',
        'erms_reference',
    ];

    protected $casts = [
        'payroll_month' => 'integer',
        'payroll_year' => 'integer',
        'prepared_at' => 'datetime',
        'initiated_at' => 'datetime',
        'verified_at' => 'datetime',
        'examined_at' => 'datetime',
        'approved_at' => 'datetime',
        'erms_submitted_at' => 'datetime',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(PayrollRunHistory::class, 'payroll_run_id', 'id')
            ->orderByDesc('id');
    }

    public function ermsExecutions(): HasMany
    {
        return $this->hasMany(PayrollErmsExecution::class, 'payroll_run_id', 'id')
            ->orderByDesc('id');
    }
}

