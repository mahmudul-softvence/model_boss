<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TwitchService
{
    protected $clientId;

    protected $clientSecret;

    protected $username;

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->clientSecret = config('services.twitch.client_secret');
        $this->username = config('services.twitch.username');
    }

    public function getAccessToken()
    {
        return Cache::remember('twitch_app_token', 5000, function () {

            $response = Http::asForm()->post(
                'https://id.twitch.tv/oauth2/token',
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ]
            );

            return $response->json()['access_token'];
        });
    }

    public function checkLiveStatus()
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer '.$token,
        ])->get('https://api.twitch.tv/helix/streams', [
            'user_login' => $this->username,
        ]);

        $data = $response->json()['data'];

        return ! empty($data)
            ? [
                'is_live' => true,
                'stream' => $data[0],
            ]
            : [
                'is_live' => false,
            ];
    }
}
