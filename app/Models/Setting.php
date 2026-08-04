<?php

namespace App\Models;

use App\Support\SocialLinks;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * Storage key for the global challenge rules shown across the app.
     */
    public const CHALLENGE_RULES_KEY = 'challenge_rules';

    /**
     * Storage key for the fixed social profile links.
     */
    public const SOCIAL_LINKS_KEY = 'social_links';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Read a stored toggle as a boolean, falling back to $default when unset.
     */
    public static function isEnabled(string $key, bool $default = false): bool
    {
        $value = static::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Retrieve the global challenge rules as a list of strings.
     *
     * @return array<int, string>
     */
    public static function getChallengeRules(): array
    {
        $value = static::where('key', self::CHALLENGE_RULES_KEY)->value('value');

        if ($value === null) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Persist the global challenge rules as a JSON encoded list of strings.
     *
     * @param  array<int, string>  $rules
     */
    public static function setChallengeRules(array $rules): void
    {
        static::updateOrCreate(
            ['key' => self::CHALLENGE_RULES_KEY],
            ['value' => json_encode(array_values($rules))],
        );
    }

    /**
     * Retrieve the fixed social links map.
     *
     * @return array<string, string>
     */
    public static function getSocialLinks(): array
    {
        $value = static::where('key', self::SOCIAL_LINKS_KEY)->value('value');

        if ($value === null) {
            return SocialLinks::defaults();
        }

        $decoded = json_decode($value, true);

        return SocialLinks::normalize(is_array($decoded) ? $decoded : []);
    }

    /**
     * Persist social links. Only provided platforms are updated; others keep their current value.
     *
     * @param  array<string, mixed>  $links
     * @return array<string, string>
     */
    public static function setSocialLinks(array $links): array
    {
        $current = static::getSocialLinks();

        foreach (SocialLinks::platforms() as $platform) {
            if (! array_key_exists($platform, $links)) {
                continue;
            }

            $value = $links[$platform];
            $current[$platform] = is_string($value) ? trim($value) : '';
        }

        static::updateOrCreate(
            ['key' => self::SOCIAL_LINKS_KEY],
            ['value' => json_encode($current)],
        );

        return $current;
    }
}
