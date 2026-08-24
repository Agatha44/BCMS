<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZreportRepostLog extends Model
{
    use HasFactory;
    
    protected $table = 'zreport_repost_log';
    
    protected $fillable = [
        'report_date',
        'znumber',
        'report_time',
        'vfd_name',
        'tin',
        'vrn',
        'reg_id',
        'daily_total',
        'cumulative_total',
        'net_amount',
        'tax_amount',
        'cash_total',
        'emoney_total',
        'transaction_count',
        'tra_status',
        'tra_ackcode',
        'tra_ackmsg',
        'tra_received_date',
        'tra_received_time',
        'test_mode',
        'tra_response_data',
        'error_message',
        'processing_status',
        'processing_time_seconds',
        'posted_at'
    ];
    
    protected $casts = [
        'report_date' => 'date',
        'report_time' => 'datetime',
        'tra_received_date' => 'date',
        'tra_received_time' => 'datetime',
        'posted_at' => 'datetime',
        'test_mode' => 'boolean',
        'daily_total' => 'integer',
        'cumulative_total' => 'integer',
        'cash_total' => 'integer',
        'emoney_total' => 'integer',
        'transaction_count' => 'integer',
        'tra_status' => 'integer',
        'tra_ackcode' => 'integer',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'processing_time_seconds' => 'decimal:3'
    ];
}

