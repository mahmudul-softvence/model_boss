<?php

namespace App\Models;

use Database\Factories\PromotionalTermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionalTerm extends Model
{
    /** @use HasFactory<PromotionalTermFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['prize', 'list'];

    protected function casts(): array
    {
        return [
            'prize' => 'integer',
            'list' => 'array',
        ];
    }

    /**
     * @return array{prize: int, list: array<int, string>}
     */
    public static function currentContent(): array
    {
        $content = static::query()->first();

        if (! $content) {
            return [
                'prize' => 0,
                'list' => [],
            ];
        }

        return [
            'prize' => (int) $content->prize,
            'list' => $content->list ?? [],
        ];
    }

    /**
     * @param  array<int, string>  $list
     */
    public static function replaceContent(int $prize, array $list): void
    {
        $payload = [
            'prize' => $prize,
            'list' => $list,
        ];

        static::query()->delete();
        static::query()->create($payload);
    }
}
