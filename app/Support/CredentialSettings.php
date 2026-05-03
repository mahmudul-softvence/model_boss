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
                'host' => 'mail.host',
                'port' => 'mail.port',
                'encryption' => 'mail.encryption',
                'username' => 'mail.username',
                'password' => 'mail.password',
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
}
