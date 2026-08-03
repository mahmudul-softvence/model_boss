<?php

namespace App\Support;

class CredentialSettings
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'mail' => [
                'host' => 'mail.mailers.smtp.host',
                'port' => 'mail.mailers.smtp.port',
                'encryption' => 'mail.mailers.smtp.scheme',
                'username' => 'mail.mailers.smtp.username',
                'password' => 'mail.mailers.smtp.password',
                'from_address' => 'mail.from.address',
                'from_name' => 'mail.from.name',
            ],
            'stripe' => [
                'key' => 'cashier.key',
                'secret' => 'cashier.secret',
                'webhook_secret' => 'cashier.webhook.secret',
            ],
            'paypal' => [
                'base_url' => 'services.paypal.base_url',
                'client_id' => 'services.paypal.client_id',
                'client_secret' => 'services.paypal.client_secret',
            ],
            'moncash' => [
                'base_url' => 'services.moncash.base_url',
                'gateway_base' => 'services.moncash.gateway_base',
                'client_id' => 'services.moncash.client_id',
                'client_secret' => 'services.moncash.client_secret',
                'api_key' => 'services.moncash.api_key',
            ],
            'bitpay' => [
                'base_url' => 'services.bitpay.base_url',
                'token' => 'services.bitpay.token',
            ],
            'twitch' => [
                'client_id' => 'services.twitch.client_id',
                'client_secret' => 'services.twitch.client_secret',
                'webhook_secret' => 'services.twitch.webhook_secret',
            ],
            'facebook' => [
                'client_id' => 'services.facebook.client_id',
                'client_secret' => 'services.facebook.client_secret',
                'redirect' => 'services.facebook.redirect',
            ],
            'google' => [
                'client_id' => 'services.google.client_id',
                'client_secret' => 'services.google.client_secret',
                'redirect' => 'services.google.redirect',
            ],
        ];
    }

    /**
     * Returns a flat map of settings table key → Laravel config key.
     *
     * @return array<string, string>
     */
    public static function configMap(): array
    {
        $map = [];

        foreach (static::groups() as $group => $fields) {
            foreach ($fields as $field => $configKey) {
                $map["credential.{$group}.{$field}"] = $configKey;
            }
        }

        return $map;
    }

    public static function settingKey(string $group, string $field): string
    {
        return "credential.{$group}.{$field}";
    }

    /**
     * Convert a stored credential value into the value Laravel config expects.
     *
     * Admin UI still stores legacy encryption labels (ssl/tls); Symfony Mailer
     * only accepts smtp/smtps as the transport scheme.
     */
    public static function toConfigValue(string $settingKey, string $value): string
    {
        if ($settingKey !== static::settingKey('mail', 'encryption')) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'ssl', 'smtps' => 'smtps',
            'tls', 'smtp', 'none', 'null', '' => 'smtp',
            default => strtolower(trim($value)),
        };
    }

    /**
     * Convert a Laravel mail scheme back to the admin encryption label.
     */
    public static function fromConfigValue(string $settingKey, mixed $value): mixed
    {
        if ($settingKey !== static::settingKey('mail', 'encryption') || ! is_string($value)) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            'smtps' => 'ssl',
            'smtp' => 'tls',
            default => $value,
        };
    }
}
