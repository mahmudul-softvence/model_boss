<?php

namespace App\Models;

use App\Enums\ChallengeMode;
use App\Enums\ChallengeStatus;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    protected $table = 'challenges';

    protected $fillable = [
        'challenge_no',
        'challenger_id',
        'mode',
        'target_player_id',
        'accepted_by_user_id',
        'accepted_at',
        'game_id',
        'amount',
        'logo',
        'memo',
        'show_real_name',
        'match_date',
        'match_time',
        'status',
        'duration_hours',
        'offer_expires_at',
        'approved_at',
        'winner_id',
        'settled_at',
    ];

    protected $casts = [
        'mode' => ChallengeMode::class,
        'status' => ChallengeStatus::class,
        'amount' => 'decimal:2',
        'show_real_name' => 'boolean',
        'duration_hours' => 'integer',
        'match_date' => 'date',
        'accepted_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function getLogoAttribute($value)
    {
        return $value ? asset('storage/'.$value) : null;
    }

    public function challenger()
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function targetPlayer()
    {
        return $this->belongsTo(User::class, 'target_player_id');
    }

    public function acceptor()
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ChallengeStatus::OFFERED->value,
            ChallengeStatus::ACCEPTED->value,
            ChallengeStatus::COMPLETED->value,
        ]);
    }

    public function scopeHolding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ChallengeStatus::PENDING->value,
            ChallengeStatus::OFFERED->value,
            ChallengeStatus::ACCEPTED->value,
        ]);
    }

    public function scopeOrderByAmountDesc(Builder $query): Builder
    {
        return $query->orderByDesc('amount');
    }

    public function scopeOrderByStatusPriority(Builder $query): Builder
    {
        return $query->orderByRaw(
            'CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END',
            [
                ChallengeStatus::PENDING->value,
                ChallengeStatus::OFFERED->value,
                ChallengeStatus::COMPLETED->value,
            ]
        );
    }

    public function isExpired(): bool
    {
        return $this->offer_expires_at !== null
            && $this->offer_expires_at->isPast();
    }
}
