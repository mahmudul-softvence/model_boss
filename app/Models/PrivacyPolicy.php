<?php

namespace App\Models;

use Database\Factories\PrivacyPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyPolicy extends Model
{
    /** @use HasFactory<PrivacyPolicyFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['title', 'content'];

    /**
     * @return array{title: string, content: string}
     */
    public static function currentContent(): array
    {
        $content = static::query()->first();

        if (! $content) {
            return [
                'title' => 'Privacy Policy',
                'content' => '',
            ];
        }

        return [
            'title' => $content->title,
            'content' => $content->content,
        ];
    }

    public static function replaceContent(string $title, string $content): void
    {
        static::query()->delete();
        static::query()->create([
            'title' => $title,
            'content' => $content,
        ]);
    }
}
