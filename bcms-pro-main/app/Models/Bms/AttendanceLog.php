<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'attendance_logs';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'pf_number',
        'ip_address',
        'time_in',
        'time_out',
        'status',
        'session_duration',
        'overtime_status',
        'is_late_arrival',
        'is_early_departure',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'is_late_arrival' => 'boolean',
        'is_early_departure' => 'boolean',
    ];
}
