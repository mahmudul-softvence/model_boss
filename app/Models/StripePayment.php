<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripePayment extends Model
{
    protected $table = 'stripe_payments';

    protected $fillable = [
        'user_id',
        'stripe_payment_id',
        'usd_amount',
        'coin_amount',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
