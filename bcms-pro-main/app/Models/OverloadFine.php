<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OverloadFine extends Model
{
    protected $table = 'overload_fine';
    
    public $timestamps = false; // Table doesn't have created_at/updated_at columns

    protected $fillable = [
        'first_name',
        'middle_name',
        'surname',
        'vehicle_num',
        'pyr_cell_num',
        'pyr_email',
        'bill_desc',
        'bill_amount',
        'bill_exp_dt',
        'contr_num',
        'ticket_num',
        'ctr_acc_num',
        'tin_number',
        'bill_status',
        'bill_gen_by',
        'bill_gen_at',
        'bill_cancel_by',
        'cancel_reason',
        'bill_cancel_date',
        'is_cancelled',
        'trx_id',
        'trx_dt_tm',
        'usd_pay_chn',
        'psp_receipt_num',
        'psp_name',
        'receipt_type',
        't_status',
        'error_code',
        'payment_method',
        'collection_office',
        'receipt_number',
        'pay_ref_id',
        'erp_status',
        'receipt_date',
        'payment_date',
        'updated_by',
        'paid_amt',
        'pyr_name',
        'http_status',
        'source'
    ];

    protected $casts = [
        'bill_exp_dt' => 'datetime',
        'bill_gen_at' => 'datetime',
        'trx_dt_tm' => 'datetime',
        'bill_cancel_date' => 'datetime',
        'receipt_date' => 'datetime',
        'payment_date' => 'datetime',
        'bill_amount' => 'float',
        'paid_amt' => 'float',
    ];

    /**
     * Get the user who created this overload fine
     */
    public function creator()
    {
        return $this->belongsTo(AuthUser::class, 'bill_gen_by');
    }

    /**
     * Get the user who cancelled this bill
     */
    public function canceller()
    {
        return $this->belongsTo(AuthUser::class, 'bill_cancel_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater()
    {
        return $this->belongsTo(AuthUser::class, 'updated_by');
    }

    /**
     * Scope to get only uncancelled bills
     */
    public function scopeActive($query)
    {
        return $query->where('is_cancelled', '0');
    }

    /**
     * Scope to get only cancelled bills
     */
    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', '1');
    }

    /**
     * Scope to get only paid bills
     */
    public function scopePaid($query)
    {
        return $query->whereNotNull('psp_receipt_num');
    }

    /**
     * Scope to get only unpaid bills
     */
    public function scopeUnpaid($query)
    {
        return $query->whereNull('psp_receipt_num');
    }

    /**
     * Scope to get failed bills
     */
    public function scopeFailed($query)
    {
        return $query->where('t_status', 'GF');
    }

    /**
     * Check if the bill is paid
     */
    public function isPaid(): bool
    {
        return !empty($this->psp_receipt_num);
    }

    /**
     * Check if the bill is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->is_cancelled == '1';
    }

    /**
     * Check if the bill has failed
     */
    public function hasFailed(): bool
    {
        return $this->t_status === 'GF';
    }
}
