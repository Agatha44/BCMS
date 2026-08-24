<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'role_permissions';
    protected $primaryKey = 'id';

    // Disable automatic timestamps since the table uses modified_at instead
    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'permission_id',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

}
