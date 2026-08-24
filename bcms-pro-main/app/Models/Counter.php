<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    use HasFactory;
    protected $table = 'counter';
    public $timestamps = false;

    protected $fillable = ['open_counter', 'close_counter', 'user_id', 'lane_id', 'shift_id', 'payment_method'];

}
