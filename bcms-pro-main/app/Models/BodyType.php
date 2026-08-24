<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PriceList;

class BodyType extends Model
{
    use HasFactory;
    protected $table = 'body_type';
    
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by'
    ];

    public function price()
    {
        return $this->hasOne(PriceList::class, 'body_type_id', 'id');
    }

    /**
     * Scope to get only active body types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
