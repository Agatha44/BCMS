<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenGate extends Model
{
    use HasFactory;
    protected $table = 'open_gate';
    public $timestamps = false;

    protected $fillable = ['user_id', 'lane_id', 'reason', 'created_at'];
}
