<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportEngineDefinition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'module_registration_id',
        'category_key',
        'name',
        'key',
        'description',
        'script',
        'handler',
        'query',
        'params',
        'output_columns',
        'options',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'params' => 'array',
        'output_columns' => 'array',
        'options' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ReportEngineModuleRegistration::class, 'module_registration_id');
    }
}
