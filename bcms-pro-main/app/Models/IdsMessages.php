<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdsMessages extends Model
{
    use HasFactory;
    
    protected $table = 'ids_messages';
    public $timestamps = false;
    
    protected $fillable = [
        'sms_source',
        'sms_recipient',
        'sms_body',
        'status',
        'sms_process',
        'created_at'
    ];
} 