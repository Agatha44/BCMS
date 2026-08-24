<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VfdToken extends Model
{
    use HasFactory;
    
    protected $table = 'vfd_token';
    public $timestamps = false;
    
    protected $fillable = [
        'token',
        'created_by',
        'created_at',
        'expires_in',
        'token_type',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
    ];
}

