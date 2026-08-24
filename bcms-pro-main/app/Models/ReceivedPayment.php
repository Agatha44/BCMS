<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ERP-facing payment rows (optional table — may not exist in all deployments).
 */
class ReceivedPayment extends Model
{
    protected $table = 'received_payments';

    public $timestamps = false;

    protected $guarded = [];
}
