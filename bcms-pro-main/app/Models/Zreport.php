<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zreport extends Model
{
    use HasFactory;
    
    protected $table = 'zreport';
    public $timestamps = false;
    
    protected $fillable = [
        'date',
        'time',
        'vrn',
        'tin',
        'name',
        'taxoffice',
        'regid',
        'znumber',
        'efdserial',
        'registrationdate',
        'user',
        'simimsi',
        'dailytotalamount',
        'gross',
        'corrections',
        'discounts',
        'surcharges',
        'ticketsvoid',
        'ticketsvoidtotal',
        'ticketfiscal',
        'ticketsnonfiscal',
        'vatrate',
        'netamount',
        'taxamount',
        'cashamount',
        'emoney_amount',
        'emoney_type',
        'cash_type',
        'vatchangenum',
        'headchangenum',
        'city',
        'mobile',
        'address',
        'ackmsg',
        'ackcode',
    ];
    
    protected $casts = [
        'date' => 'date',
        'time' => 'datetime',
        'registrationdate' => 'date',
    ];
}

