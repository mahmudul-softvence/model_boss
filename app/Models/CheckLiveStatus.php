<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckLiveStatus extends Model
{
    protected $fillable = [
        'platform_name',
        'platform_live_status',
        'mode',
        'live_started_at',
        'live_stopped_at',
    ];

    protected $casts = [
        'live_started_at' => 'datetime',
        'live_stopped_at' => 'datetime',
    ];
}
