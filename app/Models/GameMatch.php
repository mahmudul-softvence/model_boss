<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    protected $table = 'game_matches';

    protected $fillable = [
        'match_no',
        'player_one_id',
        'player_one_logo',
        'player_one_bet',
        'player_one_total',
        'player_two_id',
        'player_two_logo',
        'player_two_bet',
        'player_two_total',
        'game_id',
        'winner_id',
        'type',
        'winner_percentage',
        'loser_percentage',
        'tiktok_link',
        'twitch_link',
        'confirmation_status', // 0=pending, 1=confirmed, 2=declined
        'match_date',
        'match_time',
        'rules',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getPlayerOneLogoAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function getPlayerTwoLogoAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function playerOne()
    {
        return $this->belongsTo(User::class, 'player_one_id');
    }

    public function playerTwo()
    {
        return $this->belongsTo(User::class, 'player_two_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
