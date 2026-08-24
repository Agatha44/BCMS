<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'permissions';
    protected $primaryKey = 'id';

    // Disable automatic timestamps since the table uses modified_at instead of updated_at
    public $timestamps = false;

    protected $fillable = [
        'name',
        'controller',
        'permission',
        'route',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}
