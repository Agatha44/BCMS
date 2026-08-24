<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VfdRegistration extends Model
{
    use HasFactory;
    
    protected $table = 'vfd_registration';
    public $timestamps = false;
    
    protected $fillable = [
        'ack_code',
        'reg_id',
        'serila',
        'uin',
        'tin',
        'vrn',
        'mobile',
        'address',
        'street',
        'city',
        'country',
        'name',
        'receiptcode',
        'region',
        'routingkey',
        'gc',
        'taxoffice',
        'username',
        'password',
        'tokenpath',
        'taxcodea',
        'taxcodeb',
        'taxcodec',
        'taxcoded',
        'created_by',
    ];
}

