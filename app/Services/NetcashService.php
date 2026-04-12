<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class NetcashService
{
    /**
     * @return array{
     *     url: string,
     *     method: string,
     *     target: string,
     *     fields: array<string, string>
     * }
     */
    public function createCheckout(User $user, float $amount, string $reference): array
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        return [
            'url' => $this->requiredConfig('services.netcash.gateway_url'),
            'method' => 'POST',
            'target' => '_top',
            'fields' => [
                'M1' => $this->requiredConfig('services.netcash.service_key'),
                'M2' => config('services.netcash.vendor_key', '24ade73c-98cf-47b3-99be-cc7b867b3080'),
                'p2' => $reference,
                'p3' => $this->paymentDescription(),
                'p4' => number_format($normalizedAmount, 2, '.', ''),
                'Budget' => 'Y',
                'm4' => (string) $user->id,
                'm5' => 'coin_purchase',
                'm6' => $this->mode(),
                'm9' => $user->email ?? '',
                'm10' => '',
                'm11' => $this->normalizePhoneNumber($user->phone_number),
                'm14' => '0',
                'm15' => '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function traceTransaction(string $requestTrace): array
    {
        $response = Http::acceptJson()
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 200)
            ->get($this->requiredConfig('services.netcash.trace_url'), [
                'RequestTrace' => $requestTrace,
            ])
            ->throw();

        return $this->decodeTraceResponse($response);
    }

    protected function requiredConfig(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing Netcash configuration for [{$key}].");
        }

        return trim($value);
    }

    protected function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }

    protected function normalizePhoneNumber(?string $phoneNumber): string
    {
        if (! is_string($phoneNumber) || trim($phoneNumber) === '') {
            return '';
        }

        return Str::limit(preg_replace('/\D+/', '', $phoneNumber) ?? '', 10, '');
    }

    protected function paymentDescription(): string
    {
        $description = trim((string) config('services.netcash.description', 'Coin purchase'));

        if ($description === '') {
            $description = 'Coin purchase';
        }

        if ($this->isSandbox() && ! Str::startsWith(strtoupper($description), 'SANDBOX ')) {
            $description = 'SANDBOX '.$description;
        }

        return Str::limit($description, 50, '');
    }

    protected function mode(): string
    {
        $mode = strtolower(trim((string) config('services.netcash.mode', 'sandbox')));

        return $mode !== '' ? $mode : 'sandbox';
    }

    protected function isSandbox(): bool
    {
        return $this->mode() !== 'live';
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeTraceResponse(Response $response): array
    {
        $payload = $response->json();

        if (is_array($payload)) {
            return $payload;
        }

        $payload = json_decode($response->body(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Netcash did not return a valid trace response.');
        }

        return $payload;
    }
}
