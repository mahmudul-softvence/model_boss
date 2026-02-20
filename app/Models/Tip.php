<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tip extends Model
{
    protected $fillable = [
        'send_user_id',
        'received_user_id',
        'tip_amount',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'send_user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_user_id');
    }
}
