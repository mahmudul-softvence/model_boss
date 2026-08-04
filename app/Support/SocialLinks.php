<?php

namespace App\Support;

class SocialLinks
{
    /**
     * Fixed social platforms shown in the app footer / social row.
     *
     * @return list<string>
     */
    public static function platforms(): array
    {
        return [
            'instagram',
            'facebook',
            'linkedin',
            'tiktok',
            'twitch',
            'telegram',
            'whatsapp',
            'youtube',
        ];
    }

    /**
     * Empty defaults for every fixed platform.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return array_fill_keys(static::platforms(), '');
    }

    /**
     * Merge stored values onto the fixed platform list (unknown keys dropped).
     *
     * @param  array<string, mixed>  $links
     * @return array<string, string>
     */
    public static function normalize(array $links): array
    {
        $normalized = static::defaults();

        foreach (static::platforms() as $platform) {
            if (! array_key_exists($platform, $links)) {
                continue;
            }

            $value = $links[$platform];
            $normalized[$platform] = is_string($value) ? trim($value) : '';
        }

        return $normalized;
    }
}
