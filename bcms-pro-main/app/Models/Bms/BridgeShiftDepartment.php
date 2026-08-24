<?php

namespace App\Models\Bms;

use App\Models\Bms\Department;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgeShiftDepartment extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';
    protected $table = 'bridge_shift_department';

    protected $fillable = [
        'shift_id',
        'department_id',
        'is_active',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'shift_id' => 'integer',
        'department_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'modified_by' => 'integer',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Get the shift that owns this assignment
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(BridgeShift::class, 'shift_id');
    }

    /**
     * Get the department that owns this assignment
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * Scope to get only active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}
