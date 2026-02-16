<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $table = 'withdrawals';

    protected $fillable = [
        'user_id',
        'withdraw_no',
        'coin_amount',
        'usd_amount',
        'stripe_transfer_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
