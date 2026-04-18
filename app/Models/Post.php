<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'image',
        'description',
        'user_id',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }
}
