<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class Follower extends Pivot
{
    use SoftDeletes;

    protected $table = 'followers';

    public $incrementing = true;

    protected $fillable = [
        'follower_id',
        'following_id',
    ];
}
