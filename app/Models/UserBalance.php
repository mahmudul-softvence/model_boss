<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBalance extends Model
{
    protected $table = 'user_balances';

    protected $fillable = [
        'user_id',
        'total_earning',
        'total_referral_earning',
        'total_tip_received',
        'total_withdraw',
        'total_balance',
        'total_bet',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
