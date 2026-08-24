<?php

namespace App\Models;

use App\Models\Bms\BridgeEmployeeRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuthUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Explicitly use default connection (mysql) to avoid inheriting bcmis2 connection
    // when used in relationships with models that use bcmis2 connection
    protected $connection = 'mysql';

    // protected $connection = 'pgsql';

    protected $table = 'auth_user';

    protected $fillable = [
        'first_name',
        'middle_name',
        'surname',
        'email',
        'phone',
        'nida',
        'pf_number',
        'username',
        'status',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
        'password_reset_token',
    ];

    public function userRoles(): HasMany
    {
        return $this->hasMany(AuthUserRole::class, 'user_id');
    }

    public function activeUserRoles(): HasMany
    {
        return $this->userRoles()->assigned();
    }

    public function effectiveUserRoles(): HasMany
    {
        return $this->userRoles()->currentlyEffective();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AuthRole::class, 'auth_user_role', 'user_id', 'role_id')
            ->withPivot('is_active', 'start_date', 'end_date')
            ->wherePivot('is_active', 1)
            ->withTimestamps();
    }

    public function bridgeRoles(): HasMany
    {
        return $this->hasMany(BridgeEmployeeRole::class, 'national_id', 'nida')->active();
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->surname,
        ])));
    }
}
