<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardSequence extends Model
{
    use HasFactory;
    
    protected $table = 'card_sequence';
    public $timestamps = false;
    
    // Set the primary key to 'number' since there's no 'id' column
    protected $primaryKey = 'number';
    public $incrementing = true;
    
    protected $fillable = [
        'prefix',
        'number',
        'card_num'
    ];
} 