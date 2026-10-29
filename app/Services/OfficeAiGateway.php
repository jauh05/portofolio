<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $primary = $this->getConfig('office_ai');
        $fallback = $this->getConfig('office_ai.fallback');

        $providers = [$primary];
        if ($this->isFallbackEnabled($fallback) && $this->isValidConfig($fallback)) {
            $providers[] = $fallback;
        }

        $lastException = null;

        foreach ($providers as $index => $provider) {
            try {
                return $this->callProvider($provider, $system, $user, $json);
            } catch (OfficeAiGatewayException $exception) {
                $lastException = $exception;

                if ($index === 0 && count($providers) > 1 && $this->shouldFallback($exception)) {
                    Log::warning('Office AI primary provider failed, switching to fallback.', [
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode(),
                    ]);
                    continue;
                }

                throw $exception;
            } catch (\Throwable $throwable) {
                $lastException = new OfficeAiGatewayException('Office AI provider is unavailable. Please try again.', 503);

                if ($index === 0 && count($providers) > 1) {
                    Log::warning('Office AI primary provider threw unexpected error, switching to fallback.', [
                        'message' => $throwable->getMessage(),
                    ]);
                    continue;
                }

                throw $lastException;
            }
        }

        if ($lastException instanceof OfficeAiGatewayException) {
            throw $lastException;
        }

        throw new OfficeAiGatewayException('Office AI is not configured yet.', 422);
    }

    private function callProvider(array $provider, string $system, string $user, bool $json): string
    {
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');
        $apiKey = (string) ($provider['api_key'] ?? '');
        $model = (string) ($provider['model'] ?? '');

        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            throw new OfficeAiGatewayException('Office AI is not configured yet.', 422);
        }

        try {
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ];
            if ($json) {
                $payload['response_format'] = ['type' => 'json_object'];
            }
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
            throw new OfficeAiGatewayException($message, $response->serverError() ? 503 : 422);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new OfficeAiGatewayException('Office AI provider returned an empty response.', 502);
        }

        return trim($content);
    }

    private function shouldFallback(OfficeAiGatewayException $exception): bool
    {
        $code = $exception->getCode();
        if (in_array($code, [429, 502, 503, 504], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());
        return str_contains($message, 'timeout') || str_contains($message, 'unavailable') || str_contains($message, 'empty response') || str_contains($message, 'invalid json');
    }

    private function getConfig(string $key): array
    {
        $value = config('services.'.$key);
        return is_array($value) ? $value : [];
    }

    private function isFallbackEnabled(array $fallback): bool
    {
        if (array_key_exists('enabled', $fallback)) {
            return filter_var($fallback['enabled'], FILTER_VALIDATE_BOOL);
        }
        return true;
    }

    private function isValidConfig(array $config): bool
    {
        return ! empty($config['base_url']) && ! empty($config['api_key']) && ! empty($config['model']);
    }

    private function stripMarkdownFence(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^```(?:json)?\\s*([\\s\\S]*?)\\s*```$/i', $value, $matches)) return trim($matches[1]);
        return $value;
    }
}
