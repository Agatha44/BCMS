<?php

namespace App\Models\Bms\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollErmsExecution extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_SUCCESS = 1;

    public const STATUS_FAILED = 2;

    public const TYPE_MISCELLANEOUS = 'miscellaneous';

    public const TYPE_NET_PAY_PAYABLE = 'net_pay_payable';

    /** Prefix for per-kind deduction payables, e.g. deduction_payable:psssf */
    public const TYPE_DEDUCTION_PAYABLE = 'deduction_payable';

    public const TYPE_PAYABLE = 'payable';

    protected $connection = 'bcmis2';

    protected $table = 'payroll_erms_executions';

    protected $fillable = [
        'payroll_run_id',
        'execution_type',
        'bank_batch',
        'source_ref',
        'status',
        'erms_reference',
        'http_status',
        'error_message',
        'response_payload',
        'submitted_at',
    ];

    protected $casts = [
        'payroll_run_id' => 'integer',
        'status' => 'integer',
        'http_status' => 'integer',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id', 'id');
    }
}
