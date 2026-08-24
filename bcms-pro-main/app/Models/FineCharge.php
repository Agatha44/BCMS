<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FineCharge extends Model
{
    use HasFactory;

    protected $table = 'fine_charge';

    protected $fillable = [
        'payer_name',
        'plate_number',
        'driver_name',
        'charge_date',
        'charge_description',
        'email',
        'phone_number',
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
        'charge_date' => 'date',
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
    ];
}
