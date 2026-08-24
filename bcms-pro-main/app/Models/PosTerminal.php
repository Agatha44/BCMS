<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTerminal extends Model
{
    use HasFactory;
    
    protected $table = 'pos_terminals';
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_MAINTENANCE = 'maintenance';
    
    // Terminal type constants
    const TYPE_POS = 'POS';
    const TYPE_KIOSK = 'Kiosk';
    const TYPE_MOBILE = 'Mobile';
    
    protected $fillable = [
        'name',
        'lane_id',
        'lane_number',
        'mac_address',
        'ip_address',
        'terminal_type',
        'location',
        'status',
        'api_key',
        'last_heartbeat',
        'configuration',
        'firmware_version',
        'registered_by'
    ];
    
    protected $casts = [
        'configuration' => 'array',
        'last_heartbeat' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the lane associated with this terminal
     */
    public function lane()
    {
        return $this->belongsTo(Lane::class, 'lane_id', 'id');
    }
    
    /**
     * Get the user who registered this terminal
     */
    public function registeredBy()
    {
        return $this->belongsTo(AuthUser::class, 'registered_by', 'id');
    }
    
    /**
     * Get balance history for transactions from this terminal
     */
    public function balanceHistory()
    {
        return $this->hasMany(AccountBalanceHistory::class, 'terminal_id', 'id');
    }
    
    /**
     * Scope for active terminals
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePendingConfiguration($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_PENDING)
                ->orWhereNull('lane_id')
                ->orWhereNull('lane_number');
        });
    }

    public function isLaneConfigured(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->lane_id !== null
            && $this->lane_number !== null
            && trim((string) $this->lane_number) !== '';
    }
    
    /**
     * Scope for specific lane
     */
    public function scopeForLane($query, $laneNumber)
    {
        return $query->where('lane_number', $laneNumber);
    }
    
    /**
     * Scope for specific terminal type
     */
    public function scopeType($query, $type)
    {
        return $query->where('terminal_type', $type);
    }
    
    /**
     * Check if terminal is online (heartbeat within last 5 minutes)
     */
    public function isOnline()
    {
        if (!$this->last_heartbeat) {
            return false;
        }
        
        return $this->last_heartbeat->diffInMinutes(now()) <= 5;
    }
    
    /**
     * Update terminal heartbeat
     */
    public function updateHeartbeat()
    {
        $this->last_heartbeat = now();
        $this->save();
    }
    
    /**
     * Generate API key for terminal
     */
    public function generateApiKey()
    {
        $this->api_key = 'pos_' . bin2hex(random_bytes(32));
        $this->save();
        return $this->api_key;
    }
}
