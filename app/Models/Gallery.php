<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Gallery extends Model
{
    protected $fillable = [
        'short_video',
        'short_video_thumb',
        'description',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function getImageUrlAttribute()
    {
        return $this->short_video_thumb ? Storage::disk()->url($this->short_video_thumb) : null;
    }

    public function getVideoUrlAttribute()
    {
        return $this->short_video ? Storage::disk()->url($this->short_video) : null;
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
