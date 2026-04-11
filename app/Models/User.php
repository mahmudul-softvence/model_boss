<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\VerifyEmailQueued;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use Billable, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone_number',
        'nationality',
        'address',
        'zip_code',
        'state',
        'image',
        'provider',
        'provider_id',
        'password',
        'reference_status',
        'referral_user_id',
        'referral_no',
        'game_id',
        'social_verification_status',
        'is_player',
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
            'is_player' => 'boolean',
        ];
    }

    protected $appends = ['image_url', 'full_name'];

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

    public function moncashPayments()
    {
        return $this->hasMany(MoncashPayment::class, 'user_id');
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

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function suspension()
    {
        return $this->hasOne(UserSuspension::class);
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'followers', 'follower_id', 'following_id')->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'followers', 'following_id', 'follower_id')->withTimestamps();
    }

    public function isFollowing($userId)
    {
        return $this->following()->where('following_id', $userId)->exists();
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailQueued);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getImageUrlAttribute()
    {
        if (! $this->image) {
            return null;
        }

        return $this->image
            ? asset('storage/'.$this->image)
            : null;
    }

    public function getFullNameAttribute(): ?string
    {
        return static::formatFullName(
            $this->first_name,
            $this->middle_name,
            $this->last_name
        );
    }

    public function isSuspended(): bool
    {
        return $this->suspension &&
            (
                $this->suspension->is_permanent ||
                ($this->suspension->suspended_until?->isFuture())
            );
    }

    public static function splitFullName(?string $fullName): array
    {
        $fullName = preg_replace('/\s+/', ' ', trim((string) $fullName));

        if ($fullName === '') {
            return [
                'first_name' => null,
                'middle_name' => null,
                'last_name' => null,
            ];
        }

        $parts = explode(' ', $fullName);
        $firstName = array_shift($parts);
        $lastName = count($parts) > 0 ? array_pop($parts) : null;
        $middleName = count($parts) > 0 ? implode(' ', $parts) : null;

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
        ];
    }

    public static function formatFullName(?string $firstName, ?string $middleName = null, ?string $lastName = null): ?string
    {
        $fullName = trim(implode(' ', array_filter([
            $firstName,
            $middleName,
            $lastName,
        ])));

        return $fullName !== '' ? $fullName : null;
    }

    protected static function booted()
    {
        static::saving(function ($user) {
            $nameFields = ['first_name', 'middle_name', 'last_name'];

            if ($user->isDirty($nameFields)) {
                $user->name = static::formatFullName(
                    $user->first_name,
                    $user->middle_name,
                    $user->last_name
                );

                return;
            }

            if ($user->isDirty('name')) {
                $parts = static::splitFullName($user->name);

                $user->first_name = $parts['first_name'];
                $user->middle_name = $parts['middle_name'];
                $user->last_name = $parts['last_name'];
            }
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {

            if (empty($user->referral_no)) {
                $user->referral_no = strtoupper(Str::uuid()->toString());
            }
        });
    }
}
