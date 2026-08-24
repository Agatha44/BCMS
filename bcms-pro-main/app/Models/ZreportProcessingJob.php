<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZreportProcessingJob extends Model
{
    use HasFactory;
    
    protected $table = 'zreport_processing_jobs';
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    protected $fillable = [
        'job_id',
        'status',
        'start_date',
        'end_date',
        'batch_size',
        'test_mode',
        'total_missing',
        'total_processed',
        'total_successful',
        'total_failed',
        'current_offset',
        'remaining',
        'current_date_processing',
        'error_message',
        'last_response',
        'progress_percentage',
        'started_at',
        'completed_at',
        'estimated_seconds_remaining'
    ];
    
    protected $casts = [
        'test_mode' => 'boolean',
        'total_missing' => 'integer',
        'total_processed' => 'integer',
        'total_successful' => 'integer',
        'total_failed' => 'integer',
        'current_offset' => 'integer',
        'remaining' => 'integer',
        'batch_size' => 'integer',
        'progress_percentage' => 'decimal:2',
        'last_response' => 'array',
        'estimated_seconds_remaining' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
}

