<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 18000; // 5 hours in seconds

    public $tries = 5; // Only 5 attempts

    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct($userId)
    {
        $this->user = User::findOrFail($userId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();
        $endTime = $startTime->copy()->addHours(10);
        $iteration = 1;

        Log::info('PingJob started', [
            'user_id' => $this->user->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        // Send initial notification
        $this->sendNotification($iteration, $startTime, $endTime);

        // Loop for 5 hours, sending notifications every 10 minutes
        while (now()->lt($endTime)) {
            // Sleep for 10 minutes (600 seconds)
            sleep(600);

            // Check if we're still within the 5-hour window
            if (now()->gte($endTime)) {
                break;
            }

            $iteration++;
            $this->sendNotification($iteration, $startTime, $endTime);
        }

        // Send final notification
        Log::info('PingJob completed', [
            'user_id' => $this->user->id,
            'total_iterations' => $iteration,
            'duration' => now()->diffInMinutes($startTime) . ' minutes',
        ]);

        $this->sendFinalNotification($iteration, $startTime);
    }

    /**
     * Send a ping notification
     */
    private function sendNotification(int $iteration, $startTime, $endTime): void
    {
        $elapsed = now()->diffInMinutes($startTime);
        $remaining = now()->diffInMinutes($endTime);

        $message = "🔔 Ping #{$iteration}\n\n";
        $message .= "⏱️ Elapsed: {$elapsed} minutes\n";
        $message .= "⏳ Remaining: {$remaining} minutes\n";
        $message .= "📅 Time: " . now()->format('Y-m-d H:i:s');

        Log::info("PingJob iteration #{$iteration}", [
            'user_id' => $this->user->id,
            'elapsed_minutes' => $elapsed,
            'remaining_minutes' => $remaining,
        ]);

        try {
            $telegramService = new TelegramNotificationService();
            $telegramService->sendToAllBots($this->user, 'system_errors', $message);
        } catch (\Exception $e) {
            Log::error('Failed to send ping notification', [
                'user_id' => $this->user->id,
                'iteration' => $iteration,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send final notification when job completes
     */
    private function sendFinalNotification(int $totalIterations, $startTime): void
    {
        $duration = now()->diffInMinutes($startTime);

        $message = "✅ Ping Job Completed!\n\n";
        $message .= "📊 Total Pings: {$totalIterations}\n";
        $message .= "⏱️ Duration: {$duration} minutes\n";
        $message .= "🏁 Ended: " . now()->format('Y-m-d H:i:s');

        try {
            $telegramService = new TelegramNotificationService();
            $telegramService->sendToAllBots($this->user, 'system_errors', $message);
        } catch (\Exception $e) {
            Log::error('Failed to send final ping notification', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PingJob failed', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        try {
            $message = "❌ Ping Job Failed!\n\n";
            $message .= "Error: " . $exception->getMessage();

            $telegramService = new TelegramNotificationService();
            $telegramService->sendToAllBots($this->user, 'system_errors', $message);
        } catch (\Exception $e) {
            Log::error('Failed to send failure notification', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
