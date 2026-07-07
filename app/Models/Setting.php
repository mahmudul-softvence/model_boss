<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * Storage key for the global challenge rules shown across the app.
     */
    public const CHALLENGE_RULES_KEY = 'challenge_rules';

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
}
