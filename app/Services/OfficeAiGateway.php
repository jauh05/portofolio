<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OfficeAiGateway
{
    public function completeText(string $system, string $user): string
    {
        return $this->requestCompletion($system, $user, false);
    }

    public function completeJson(string $system, string $user): array
    {
        $json = $this->stripMarkdownFence($this->requestCompletion($system, $user, true));
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new OfficeAiGatewayException('Office AI provider returned invalid JSON.', 502);
        }

        return $decoded;
    }

    private function requestCompletion(string $system, string $user, bool $json): string
    {
        $baseUrl = rtrim((string) config('services.office_ai.base_url'), '/');
        $apiKey = (string) config('services.office_ai.api_key');
        $model = (string) config('services.office_ai.model');
        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            throw new OfficeAiGatewayException('Office AI is not configured yet.', 422);
        }

        try {
            $payload = [
                'model' => $model,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            ];
            if ($json) $payload['response_format'] = ['type' => 'json_object'];
            $response = Http::acceptJson()->withToken($apiKey)->timeout(90)->post($baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException) {
            throw new OfficeAiGatewayException('Office AI provider timed out. Please try again.', 504);
        } catch (\Throwable) {
            throw new OfficeAiGatewayException('Office AI provider is unavailable. Please try again.', 503);
        }

        if (! $response->successful()) {
            $message = match ($response->status()) {
                401, 403 => 'Office AI provider rejected its configured credentials.',
                429 => 'Office AI provider is rate-limited. Please try again shortly.',
                default => $response->serverError() ? 'Office AI provider is temporarily unavailable.' : 'Office AI provider rejected this request.',
            };
            throw new OfficeAiGatewayException($message, $response->status() >= 500 ? 503 : 422);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new OfficeAiGatewayException('Office AI provider returned an empty response.', 502);
        }

        return trim($content);
    }

    private function stripMarkdownFence(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^```(?:json)?\\s*([\\s\\S]*?)\\s*```$/i', $value, $matches)) return trim($matches[1]);
        return $value;
    }
}
