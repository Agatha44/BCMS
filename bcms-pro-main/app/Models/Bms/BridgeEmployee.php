<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgeEmployee extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_employee';

    protected $primaryKey = 'national_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'national_id',
        'pfno',
        'fname',
        'mname',
        'sname',
        'gender',
        'title',
        'dob',
        'maritalstatus',
        'domicile',
        'employment_place',
        'nationality',
        'passport_no',
        'mobile',
        'email',
        'educational_level_id',
        'scheme_id',
        'emptype_id',
        'bank_id',
        'account_no',
        'cby',
        'cdate',
        'eby',
        'edate',
        'employee_status',
        'district_id',
        'department_id',
        'ext_number',
        'ssn',
        'pic',
        'sig',
        'basicsalary',
        'username',
        'pwd',
        'llogin',
        'lcount',
        'is_first_time',
        'account_status',
        'sby',
        'sdate',
        'aby',
        'adate',
        'accesstoken',
        'person_id',
        'pid',
        'pfno2',
        'tin',
        'current_qualification',
        'last_promotion_date',
        'promotion_due_date',
        'last_token_date',
        'health_insurance_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'cdate' => 'datetime',
        'edate' => 'datetime',
        'sdate' => 'datetime',
        'adate' => 'datetime',
        'llogin' => 'datetime',
        'last_promotion_date' => 'date',
        'promotion_due_date' => 'date',
        'last_token_date' => 'datetime',
        'basicsalary' => 'decimal:2',
        'lcount' => 'integer',
        'is_first_time' => 'boolean',
        'scheme_id' => 'integer',
        'emptype_id' => 'integer',
        'bank_id' => 'integer',
        'district_id' => 'integer',
        'department_id' => 'integer',
        'person_id' => 'integer',
        'pid' => 'integer',
        'health_insurance_id' => 'integer',
    ];

    protected $appends = [
        'full_name',
    ];

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        $name = trim($this->fname ?? '');
        if (!empty($this->mname)) {
            $name .= ' ' . trim($this->mname);
        }
        $name .= ' ' . trim($this->sname ?? '');
        return trim($name);
    }

    /**
     * Scope to get active employees
     */
    public function scopeActive($query)
    {
        return $query->whereIn('employee_status', \App\Constants\EmployeeStatus::activeValues());
    }

    /**
     * Scope to get employees by PFNO
     */
    public function scopeByPfno($query, $pfno)
    {
        return $query->where('pfno', $pfno)
            ->orWhere('pfno2', $pfno);
    }

    /**
     * Scope to get employees by National ID
     */
    public function scopeByNationalId($query, $nationalId)
    {
        return $query->where('national_id', $nationalId);
    }

    /**
     * Scope to get employees by username
     */
    public function scopeByUsername($query, $username)
    {
        return $query->where('username', $username);
    }

    /**
     * Scope to get employees by email
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to get employees by mobile
     */
    public function scopeByMobile($query, $mobile)
    {
        return $query->where('mobile', $mobile);
    }

    /**
     * Get all role assignments for this employee
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(BridgeEmployeeRole::class, 'national_id', 'national_id');
    }

    /**
     * Get active role assignments for this employee
     */
    public function activeRoleAssignments(): HasMany
    {
        return $this->hasMany(BridgeEmployeeRole::class, 'national_id', 'national_id')
            ->where('is_active', true);
    }

    /**
     * Get all roles assigned to this employee (many-to-many through pivot)
     */
    public function roles(): BelongsToMany
    {
        // Pivot table (bridge_employee_role) does not use Laravel's default
        // timestamp columns (created_at / updated_at). It uses created_at and
        // modified_at instead, and the model itself has $timestamps = false.
        // Using withTimestamps() here makes Eloquent expect an updated_at
        // column on the pivot, which causes SQL errors when loading roles.
        //
        // We therefore only include the explicit pivot fields we need and
        // avoid withTimestamps() so role loading works correctly.
        return $this->belongsToMany(Role::class, 'bridge_employee_role', 'national_id', 'role_id', 'national_id', 'id')
            ->wherePivot('is_active', true)
            ->withPivot(['id', 'is_active', 'created_at', 'modified_at', 'description', 'revoked_at', 'revoked_by']);
    }

    /**
     * Get the bank associated with this employee
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'bank_id');
    }

    /**
     * Get the employment type associated with this employee
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(BridgeEmploymentType::class, 'emptype_id', 'emptype_id');
    }

    /**
     * Get the scheme associated with this employee
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'scheme_id', 'id');
    }

    /**
     * Get the department associated with this employee
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }
}
