<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportEngineModuleRegistration extends Model
{
    protected $fillable = [
        'bridge_module_id',
        'menu_label',
        'route_slug',
        'module_path_prefix',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function definitions(): HasMany
    {
        return $this->hasMany(ReportEngineDefinition::class, 'module_registration_id');
    }
}
