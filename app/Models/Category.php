<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'name',
        'image',
    ];

    public function getImageAttribute($value)
    {
        if (! $value || Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $imagePath = Str::startsWith($value, 'public/')
            ? $value
            : 'public/' . ltrim($value, '/');

        return url($imagePath);
    }
}
