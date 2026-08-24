<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountCardHistory extends Model
{
    use HasFactory;
    
    protected $table = 'account_card_history';
    
    // Action constants
    const ACTION_LINKED = 'linked';
    const ACTION_UNLINKED = 'unlinked';
    const ACTION_UPDATED = 'updated';
    
    protected $fillable = [
        'account_id',
        'card_reference',
        'action',
        'old_card_reference',
        'new_card_reference',
        'reason',
        'performed_by'
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the account that this history belongs to
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }
    
    /**
     * Get the user who performed this action
     */
    public function performedBy()
    {
        return $this->belongsTo(AuthUser::class, 'performed_by', 'id');
    }
    
    /**
     * Scope for specific actions
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }
    
    /**
     * Scope for specific account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}
