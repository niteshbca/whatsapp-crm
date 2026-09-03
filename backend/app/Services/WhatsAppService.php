<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $baseUrl;

    protected int $timeout;

    protected int $connectTimeout;

    protected int $sendTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.whatsapp.url'), '/');
        $this->sendTimeout = (int) config('services.whatsapp.send_timeout', 120);
        // Quick timeouts for lightweight control calls (status/connect/logout)
        // so a slow or restarting WhatsApp service never blocks the backend.
        $this->timeout = (int) config('services.whatsapp.timeout', 8);
        $this->connectTimeout = (int) config('services.whatsapp.connect_timeout', 3);
    }    public function isReachable(): bool
    {
        return ! array_key_exists('error', $this->status());
    }

    /**
     * @return array<string, mixed>
     */
    public function status(?int $companyId = null): array
    {
        try {
            $url = "{$this->baseUrl}/api/status";
            if ($companyId !== null) {
                $url .= '?company_id=' . urlencode((string) $companyId);
            }

            $response = Http::timeout($this->timeout)->connectTimeout($this->connectTimeout)->get($url);

            if ($response->ok()) {
                return $response->json() ?? [];
            }

            return ['error' => 'WhatsApp service returned '.$response->status()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function connect(?int $companyId = null, ?string $sessionName = null): array
    {
        try {
            $payload = [];
            if ($companyId !== null) {
                $payload['company_id'] = $companyId;
            }
            if ($sessionName !== null) {
                $payload['session_name'] = $sessionName;
            }

            $response = Http::timeout($this->timeout)->connectTimeout($this->connectTimeout)->post("{$this->baseUrl}/api/connect", $payload);

            return $response->json() ?? ['error' => $response->body()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function logout(?int $companyId = null): array
    {
        try {
            $payload = [];
            if ($companyId !== null) {
                $payload['company_id'] = $companyId;
            }

            $response = Http::timeout($this->timeout)->connectTimeout($this->connectTimeout)->post("{$this->baseUrl}/api/logout", $payload);

            return $response->json() ?? ['error' => $response->body()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send a single message through the Node service.
     *
     * @return array{ok: bool, error?: string, message_id?: string}
     */
    public function send(string $number, string $message, ?string $mediaPath = null, ?int $companyId = null): array
    {
        try {
            $payload = [
                'to' => $number,
                'message' => $message,
                'media_path' => $mediaPath,
            ];
            if ($companyId !== null) {
                $payload['company_id'] = $companyId;
            }

            $response = Http::timeout($this->sendTimeout)->post("{$this->baseUrl}/api/send", $payload);

            $body = $response->json();

            if ($response->ok() && ($body['ok'] ?? false)) {
                return ['ok' => true, 'message_id' => $body['message_id'] ?? null];
            }

            return ['ok' => false, 'error' => $body['error'] ?? 'WhatsApp send failed'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Ask the Node service to begin the sending loop for a campaign.
     *
     * @param  array<int, array{id: int, number: string, name: string|null}>  $recipients
     * @return array{ok: bool, error?: string}
     */
    public function startCampaign(string $campaignId, array $recipients, string $template, ?string $mediaPath = null, int $delayMin = 2, int $delayMax = 5, ?int $companyId = null, array $mediaPaths = []): array
    {
        try {
            $payload = [
                'campaignId' => $campaignId,
                'recipients' => $recipients,
                'template' => $template,
                'media_path' => $mediaPath,
                'media_paths' => $mediaPaths,
                'delay_min' => $delayMin,
                'delay_max' => $delayMax,
            ];
            if ($companyId !== null) {
                $payload['company_id'] = $companyId;
            }

            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/campaign/start", $payload);

            $body = $response->json();

            return ['ok' => (bool) ($body['ok'] ?? false), 'error' => $body['error'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, error?: string, status?: string}
     */
    public function controlCampaign(string $campaignId, string $command): array
    {
        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/campaign/control", [
                'campaignId' => $campaignId,
                'command' => $command,
            ]);

            return $response->json() ?? ['ok' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}