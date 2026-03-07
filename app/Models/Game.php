<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Game extends Model
{
    protected $table = 'games';

    protected $fillable = [
        'name',
        'image',
        'category_id',
    ];

    public function getImageAttribute($value)
    {
        if (! $value || Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return url(ltrim($value, '/'));
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function match()
    {
        return $this->hasMany(GameMatch::class, 'game_id');
    }

}
