<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrfVote extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'prf_votes';

    protected $fillable = [
        'prf_detail_id',
        'vote_id',
        'amount',
        'segment3_desc',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the PRF detail this vote belongs to
     */
    public function prfDetail(): BelongsTo
    {
        return $this->belongsTo(OvertimePrfDetail::class, 'prf_detail_id', 'id');
    }
}

