<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReprintReceipt extends Model
{
    use HasFactory;
    protected $table = 'reprint_receipt';
    public $timestamps = false;

    protected $fillable = ['reason', 'user_id', 'receipt_no', 'lane_id', 'created_at'];

}
