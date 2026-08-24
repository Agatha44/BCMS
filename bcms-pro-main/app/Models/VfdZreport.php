<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VfdZreport extends Model
{
    use HasFactory;
    
    protected $table = 'vfd_zreport';
    public $timestamps = false;
    
    protected $fillable = [
        'znumber',
        'received_date',
        'received_time',
        'ackcode',
        'ackmsg',
        'created_at',
    ];
    
    protected $casts = [
        'received_date' => 'date',
        'received_time' => 'datetime',
        'created_at' => 'datetime',
    ];
}

