<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\models\AuthUser;

class Role extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'roles';
    protected $primaryKey = 'id';

    // Disable automatic timestamps since the table uses modified_at instead
    public $timestamps = false;

    protected $fillable = [
        'role_name',
        'role_description',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'modified_at' => 'datetime',
    ];

    public function creator(){
        return $this->belongsTo(AuthUser::class, 'created_by');
    }

    /**
     * Get the modules that this role has access to (many-to-many relationship)
     * One role can have access to one or more modules
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            BridgeModule::class,
            'bridge_module_role',
            'role_id',
            'module_id',
            'id',
            'id'
        )
        ->withPivot('is_active', 'created_by', 'modified_by', 'created_at', 'modified_at')
        ->withTimestamps();
    }

    /**
     * Get the active modules that this role has access to
     */
    public function activeModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('is_active', true);
    }

    /**
     * Get the bridge module role pivot records
     */
    public function moduleRoles(): HasMany
    {
        return $this->hasMany(BridgeModuleRole::class, 'role_id');
    }
}
