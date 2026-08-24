<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountBalanceHistory extends Model
{
    use HasFactory;
    
    protected $table = 'account_balance_history';
    
    // Transaction type constants
    const TYPE_DEDUCTION = 'deduction';
    const TYPE_TOP_UP = 'top_up';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_REFUND = 'refund';
    
    protected $fillable = [
        'account_id',
        'transaction_id',
        'card_reference',
        'transaction_type',
        'previous_balance',
        'transaction_amount',
        'new_balance',
        'lane_number',
        'terminal_id',
        'reference_number',
        'description',
        'metadata',
        'processed_by'
    ];
    
    protected $casts = [
        'previous_balance' => 'decimal:2',
        'transaction_amount' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'metadata' => 'array',
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
     * Get the user who processed this transaction
     */
    public function processedBy()
    {
        return $this->belongsTo(AuthUser::class, 'processed_by', 'id');
    }
    
    /**
     * Get the POS terminal that processed this transaction
     */
    public function terminal()
    {
        return $this->belongsTo(PosTerminal::class, 'terminal_id', 'id');
    }
    
    /**
     * Get the toll transaction that this balance history is linked to
     */
    public function tollTransaction()
    {
        return $this->belongsTo(TollTransaction::class, 'transaction_id', 'id');
    }
    
    /**
     * Scope for specific transaction types
     */
    public function scopeTransactionType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }
    
    /**
     * Scope for specific account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }
    
    /**
     * Scope for specific card reference
     */
    public function scopeForCard($query, $cardReference)
    {
        return $query->where('card_reference', $cardReference);
    }
    
    /**
     * Scope for specific lane/terminal
     */
    public function scopeForLane($query, $laneNumber)
    {
        return $query->where('lane_number', $laneNumber);
    }
    
    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
