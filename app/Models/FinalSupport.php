<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinalSupport extends Model
{
    protected $table = 'final_supports';

    protected $fillable = [
        'support_id',
        'match_id',
        'match_no',
        'supported_player_id',
        'user_id',
        'coin_amount',
        'result',
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function supportedPlayer()
    {
        return $this->belongsTo(User::class, 'supported_player_id');
    }

    public function supporter()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
