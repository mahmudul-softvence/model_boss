<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTransaction extends Model
{
    protected $table = 'coin_transactions';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'reference',
        'invoice_pdf'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
