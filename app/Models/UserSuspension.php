<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSuspension extends Model
{
    protected $fillable = [
        'user_id',
        'suspended_until',
        'is_permanent',
        'reason',
        'note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
