<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchForVoting extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_one_id',
        'player_two_id',
        'total_vote',
        'start_time',
        'end_time',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function playerOne()
    {
        return $this->belongsTo(User::class, 'player_one_id');
    }

    public function playerTwo()
    {
        return $this->belongsTo(User::class, 'player_two_id');
    }
}
