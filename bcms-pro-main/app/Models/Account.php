<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Account extends Authenticatable

{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'account';

    protected $fillable = [
        'nida',
        'first_name',
        'middle_name',
        'surname',
        'email',
        'phone',
        'card_reference',
        'password_hash',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by'
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->surname;
    }
    
    /**
     * Get the card history for this account
     */
    public function cardHistory()
    {
        return $this->hasMany(AccountCardHistory::class, 'account_id', 'id')->orderBy('created_at', 'desc');
    }
    
    /**
     * Get the latest card history entry
     */
    public function latestCardHistory()
    {
        return $this->hasOne(AccountCardHistory::class, 'account_id', 'id')->latest();
    }
    
    /**
     * Get the balance history for this account
     */
    public function balanceHistory()
    {
        return $this->hasMany(AccountBalanceHistory::class, 'account_id', 'id')->orderBy('created_at', 'desc');
    }
    
    /**
     * Get the latest balance history entry
     */
    public function latestBalanceHistory()
    {
        return $this->hasOne(AccountBalanceHistory::class, 'account_id', 'id')->latest();
    }
}
