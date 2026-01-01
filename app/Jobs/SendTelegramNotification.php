<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotification implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $event,
        public array $data
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramNotificationService $telegramService): void
    {
        $message = $telegramService->formatJobMessage($this->event, $this->data);
        $telegramService->sendToAllBots($this->user, $this->event, $message);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('Failed to send Telegram notification', [
            'user_id' => $this->user->id,
            'event' => $this->event,
            'error' => $exception?->getMessage(),
        ]);
    }
}
