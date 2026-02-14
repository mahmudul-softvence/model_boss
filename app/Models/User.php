<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\VerifyEmailQueued;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, Billable;

    protected $fillable = [
        'name',
        'email',
        'image',
        'provider',
        'provider_id',
        'password',
        'suspended_until',
        'is_permanent_suspended',
        'suspension_reason',
        'suspension_note'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function userBalance()
    {
        return $this->hasOne(UserBalance::class, 'user_id');
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class, 'user_id');
    }

    public function stripePayments()
    {
        return $this->hasMany(StripePayment::class, 'user_id');
    }

    public function coinTransactions()
    {
        return $this->hasMany(CoinTransaction::class, 'user_id');
    }

    public function asPlayerOne()
    {
        return $this->hasMany(GameMatch::class, 'player_one_id');
    }

    public function asPlayerTwo()
    {
        return $this->hasMany(GameMatch::class, 'player_two_id');
    }

    public function MatchWon()
    {
        return $this->hasMany(GameMatch::class, 'winner_id');
    }


    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailQueued());
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }


    public function isSuspended(): bool
    {
        if ($this->is_permanent_suspended) {
            return true;
        }

        if ($this->suspended_until && Carbon::parse($this->suspended_until)->isFuture()) {
            return true;
        }

        return false;
    }
}
