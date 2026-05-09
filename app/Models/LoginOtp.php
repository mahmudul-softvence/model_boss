<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginOtp extends Model
{
    protected $fillable = [
        'email',
        'otp',
    ];

    protected $hidden = [
        'otp',
    ];
}
