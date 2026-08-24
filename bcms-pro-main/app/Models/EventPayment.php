<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EventPayment extends Model
{
    use HasFactory;

    protected $table = 'event_payment';

    protected $fillable = [
        'payer_name',
        'receiver_name',
        'event_date',
        'email',
        'phone_number',
        'event_description',
        'amount',
        'error_code',
        't_status',
        'control_num',
        'psp_receipt_num',
        'psp_name',
        'is_cancelled',
        'bill_status',
        'ctr_acc_num',
        'usd_pay_chn',
        'trx_dt_tm',
        'trx_id',
        'cancel_reason',
        'bill_cancel_date',
        'bill_cancel_by',
        'bill_exp_dt',
        'bill_gen_at',
        'created_by',
        'receipt_number',
        'pyr_cell_num',
        'pay_ref_id',
        'erp_status',
        'receipt_date',
        'payment_date',
        'paid_amt',
        'pyr_name',
        'http_status',
        'source',
    ];

    protected $casts = [
        'event_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amt' => 'decimal:2',
        'trx_dt_tm' => 'datetime',
        'bill_cancel_date' => 'datetime',
        'bill_exp_dt' => 'datetime',
        'bill_gen_at' => 'datetime',
        'receipt_date' => 'datetime',
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'trx_id' => 'integer',
        'bill_cancel_by' => 'integer',
        'http_status' => 'integer',
        'source' => 'integer',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_cancelled', '0');
    }

    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', '1');
    }

    public function scopePaid($query)
    {
        return $query->whereNotNull('psp_receipt_num');
    }

    public function scopeUnpaid($query)
    {
        return $query->whereNull('psp_receipt_num');
    }

    public function scopeFailed($query)
    {
        return $query->where('t_status', 'GF');
    }

    // Accessors
    public function getStatusAttribute()
    {
        if ($this->is_cancelled == '1') {
            return 'cancelled';
        }
        
        if (!empty($this->psp_receipt_num)) {
            return 'paid';
        }
        
        if ($this->t_status == 'GF') {
            return 'failed';
        }
        
        return 'pending';
    }
}
