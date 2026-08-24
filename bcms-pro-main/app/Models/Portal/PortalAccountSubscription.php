<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class PortalAccountSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'portal_service_subscription_id', 'portal_user_id', 'account_number'
    ];


}
