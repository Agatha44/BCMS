<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TollCapture extends Model
{
    use HasFactory;

    protected $table = 'toll_capture';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_CANCELLED = 'cancelled';

    public $timestamps = false;

    protected $fillable = [
        'plate_no',
        'lane_id',
        'lane_number',
        'body_type_id',
        'vehicle_id',
        'amount',
        'image',
        'status',
        'shift_id',
        'user_id',
        'created_at',
        'updated_at',
    ];

    public function bodyType()
    {
        return $this->belongsTo(BodyType::class, 'body_type_id', 'id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function lane()
    {
        return $this->belongsTo(Lane::class, 'lane_id', 'id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Directory for ANPR capture files (same resolution order as {@see Vehicle::getImageBasePath()}).
     */
    public static function getImageBasePath(): string
    {
        $configured = config('toll_capture.images.base_path');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return Vehicle::getImageBasePath();
    }
}
