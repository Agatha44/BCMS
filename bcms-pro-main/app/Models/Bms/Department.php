<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'departments';

    protected $primaryKey = 'department_id';

    public $incrementing = true;

    // Disable updated_at since the column was removed from the table
    const UPDATED_AT = null;

    protected $fillable = [
        'department_name',
        'is_active',
        'created_by',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'modified_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

