<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(public string $message)
    {
    }

    public function handle(): void
    {
        $enabled = config('services.telegram.enabled');
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$enabled || !$token || !$chatId) {
            return;
        }

        try {
            $response = Http::timeout(5)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $this->message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if (!$response->successful()) {
                Log::warning('Telegram notification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification error: ' . $e->getMessage());
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);
        }
    }
}
