<?php

namespace App\Models;

use App\Models\Bms\BridgeModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthRoleModule extends Model
{
    protected $connection = 'bcmis2';

    protected $table = 'auth_role_module';

    protected $fillable = [
        'auth_role_id',
        'module_id',
        'is_active',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'auth_role_id' => 'integer',
        'module_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'modified_by' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(BridgeModule::class, 'module_id');
    }

    public function authRole(): BelongsTo
    {
        return $this->belongsTo(AuthRole::class, 'auth_role_id');
    }
}
