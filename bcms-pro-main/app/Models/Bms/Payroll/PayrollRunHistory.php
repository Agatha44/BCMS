<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunHistory extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'payroll_runs_history';

    public $timestamps = false;

    protected $fillable = [
        'payroll_run_id',
        'action',
        'status',
        'workflow_status',
        'performed_by',
        'performed_by_role',
        'comment',
        'created_at',
    ];

    protected $casts = [
        'payroll_run_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'id');
    }

    public function getTimestampAttribute()
    {
        return $this->created_at;
    }
}

