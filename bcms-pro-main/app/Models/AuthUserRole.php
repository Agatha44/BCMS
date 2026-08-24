<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthUserRole extends Model
{
    protected $table = 'auth_user_role';

    protected $fillable = [
        'user_id',
        'role_id',
        'is_active',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(AuthRole::class, 'role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('auth_user_role.is_active', 1);
    }

    public function scopeCurrentlyEffective(Builder $query, ?Carbon $on = null): Builder
    {
        $date = ($on ?? now())->toDateString();

        return $query->assigned()
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $date);
            })
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            });
    }

    public function isCurrentlyEffective(?Carbon $on = null): bool
    {
        if ((int) $this->is_active !== 1) {
            return false;
        }

        $today = ($on ?? now())->startOfDay();

        if ($this->start_date && $this->start_date->gt($today)) {
            return false;
        }

        if ($this->end_date && $this->end_date->lt($today)) {
            return false;
        }

        return true;
    }
}
