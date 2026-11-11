<?php

namespace Modules\PaymentInstamojo\Services;

use Exception;
use Illuminate\Support\Facades\Http;

abstract class AbstractInstamojoGateway
{
    protected string $clientId;
    protected string $clientSecret;
    protected int $mode;
    protected string $apiBase;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->clientId = (string) (get_option('instamojo_client_id') ?: get_option('instamojo_api_key') ?: '');
        $this->clientSecret = (string) (get_option('instamojo_client_secret') ?: get_option('instamojo_auth_token') ?: '');
        $this->mode = (int) get_option('instamojo_environment', 0);
        $this->apiBase = $this->mode === 1
            ? 'https://api.instamojo.com/v2/'
            : 'https://test.instamojo.com/v2/';
    }

    protected function request(string $method, string $path, array $payload = [], array $headers = []): array
    {
        $response = Http::withoutVerifying()
            ->timeout(30)
            ->withHeaders($headers)
            ->send($method, rtrim($this->apiBase, '/').'/'.ltrim($path, '/'), [
                'form_params' => $payload,
                'json' => $payload,
            ]);

        if (! $response->successful()) {
            throw new Exception($response->body() ?: __('Instamojo request failed.'));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    protected function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::withoutVerifying()
            ->timeout(30)
            ->asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->apiBase.'oauth2/token/', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new Exception(__('Unable to retrieve Instamojo access token.'));
        }

        $this->accessToken = (string) $response->json('access_token');

        return $this->accessToken;
    }
}
