<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TollBundle extends Model
{
    use HasFactory;

    protected $table = 'toll_bundles';
    
    protected $fillable = [
        'bundle_description',
        'duration',
        'created_by',
        'updated_by',
        'status',
        'sw_desc'
    ];

    public function calculateExpireDate($start_date, $duration)
    {
        $start_date = Carbon::parse($start_date);
        return $start_date->addDays($duration);
    }
}
