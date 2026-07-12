<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'voting_time',
        'vote_start_time',
        'match_type',
        'challenge_id',
        'pin_to_top',
        'remove_status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'voting_time' => 'datetime',
    ];

    public function getPlayerOneLogoAttribute($value)
    {
        return $value ? Storage::disk()->url($value) : null;
    }

    public function getPlayerTwoLogoAttribute($value)
    {
        return $value ? Storage::disk()->url($value) : null;
    }

    /**
     * Newest-created match first, then pinned matches, then the rest newest first.
     * The CASE ranks the single most recently created match 0 and all others 1.
     */
    public function scopeOrderByNewestThenPinned(Builder $query): Builder
    {
        $newestMatchFirst = <<<'SQL'
            CASE
                WHEN id = (SELECT id FROM game_matches ORDER BY created_at DESC LIMIT 1)
                THEN 0
                ELSE 1
            END
        SQL;

        return $query
            ->orderByRaw($newestMatchFirst)
            ->orderByDesc('pin_to_top')
            ->orderByDesc('id');
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

    public function challenge()
    {
        return $this->belongsTo(Challenge::class, 'challenge_id');
    }
}
