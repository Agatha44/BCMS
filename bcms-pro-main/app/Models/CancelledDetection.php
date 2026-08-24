<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancelledDetection extends Model
{
    use HasFactory;
    protected $table = 'cancelled_detection';
    public $timestamps = false;

    protected $fillable = ['plate_no', 'user_id', 'body_type_id', 'lane_id','shift_id', 'amount', 'created_at'];
}
