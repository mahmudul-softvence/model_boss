<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    protected $fillable = [
        'short_video',
        'short_video_thumb',
        'description'
    ];
}
