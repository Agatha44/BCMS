<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TollCaptureHistory extends Model
{
    use HasFactory;

    protected $table = 'toll_capture_history';

    public $timestamps = false;

    protected $fillable = [
        'toll_capture_id',
        'plate_no',
        'lane_id',
        'lane_number',
        'body_type_id',
        'vehicle_id',
        'amount',
        'image',
        'shift_id',
        'user_id',
        'account_no',
        'payment_method',
        'toll_transaction_id',
        'receipt_num',
        'notes',
        'captured_at',
        'paid_at',
        'created_at',
    ];

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class, 'body_type_id', 'id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }
}
