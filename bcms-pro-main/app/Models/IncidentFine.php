<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentFine extends Model
{
    protected $table = 'incident_fine';

    protected $fillable = [
        'nature_incident',
        'driver_name',
        'vehicle_owner',
        'plate_number',
        'payment_type',
        'phone_number',
        'police_rb',
        'owner_name',
        'amount',
        'incident_date',
        'error_code',
        't_status',
        'control_num',
        'bill_exp_dt',
        'bill_gen_at',
        'created_by',
        'email',
        'insurance_name',
        'psp_receipt_num',
        'psp_name',
        'is_cancelled',
        'bill_status',
        'ctr_acc_num',
        'usd_pay_chn',
        'trx_dt_tm',
        'trx_id',
        'collection_office',
        'cancel_reason',
        'bill_cancel_date',
        'bill_cancel_by',
        'payer_name',
        'receipt_type',
        'payment_method',
        'pyr_cell_num',
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
        'incident_date' => 'datetime',
        'bill_exp_dt' => 'datetime',
        'bill_gen_at' => 'datetime',
        'trx_dt_tm' => 'datetime',
        'bill_cancel_date' => 'datetime',
        'receipt_date' => 'datetime',
        'payment_date' => 'datetime',
        'amount' => 'float',
        'paid_amt' => 'float',
    ];

    /**
     * Get the user who created this incident fine
     */
    public function creator()
    {
        return $this->belongsTo(AuthUser::class, 'created_by');
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

