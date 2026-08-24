<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;
    protected $table = 'receipt';
    protected $primaryKey = 'number';
    public $timestamps = false;
    public $incrementing = true;

    protected $fillable = ['prefix', 'receipt_num'];

}
