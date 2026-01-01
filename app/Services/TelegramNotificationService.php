<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTelegramBot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Send a message via a specific Telegram bot
     */
    public function send(UserTelegramBot $bot, string $message): array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                    'chat_id' => $bot->chat_id,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);

            if ($response->successful()) {
                $bot->update(['last_notification_at' => now()]);
                return [
                    'success' => true,
                    'message' => 'Message sent successfully',
                ];
            }

            $responseData = $response->json();
            $errorMessage = $responseData['description'] ?? 'Unknown Telegram API error';

            // Parse common Telegram API errors
            if (str_contains($errorMessage, 'chat not found')) {
                $errorMessage = 'Chat ID not found. Please verify your Chat ID is correct.';
            } elseif (str_contains($errorMessage, 'bot was blocked')) {
                $errorMessage = 'Bot was blocked by the user. Please unblock the bot in Telegram.';
            } elseif (str_contains($errorMessage, 'Unauthorized')) {
                $errorMessage = 'Invalid Bot Token. Please verify your bot token is correct.';
            } elseif (str_contains($errorMessage, 'Bad Request')) {
                $errorMessage = 'Invalid request. ' . ($responseData['description'] ?? '');
            }

            Log::error('Telegram API error', [
                'bot_id' => $bot->id,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $responseData['error_code'] ?? $response->status(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Failed to connect to Telegram API', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection error: Unable to reach Telegram servers. Please check your internet connection.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram notification', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send notification to all active bots for a user that are subscribed to the event
     */
    public function sendToAllBots(User $user, string $event, string $message): array
    {
        $settings = $user->getOrCreateSettings();

        // Check if Telegram notifications are globally enabled
        if (!$settings->telegram_notifications_enabled) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => true,
                'reason' => 'Telegram notifications are disabled in settings',
            ];
        }

        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $bots = $user->activeTelegramBots()
            ->get()
            ->filter(fn($bot) => $bot->shouldNotify($event));

        foreach ($bots as $bot) {
            $result = $this->send($bot, $message);

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'bot_name' => $bot->name ?? 'Unnamed Bot',
                    'error' => $result['message'],
                ];
            }
        }

        return $results;
    }

    /**
     * Format a job-related message for Telegram
     */
    public function formatJobMessage(string $event, array $data): string
    {

        $eventLabels = [
            'job_started' => '🚀 Job Started',
            'job_completed' => '✅ Job Completed',
            'job_failed' => '❌ Job Failed',
            'job_paused' => '⏸️ Job Paused',
            'job_resumed' => '▶️ Job Resumed',
            'progress_25' => '📊 25% Progress',
            'progress_50' => '📊 50% Progress',
            'progress_75' => '📊 75% Progress',
            'system_errors' => '⚠️ System Error',
        ];

        $title = $eventLabels[$event] ?? '📢 Notification';

        $message = "<b>{$title}</b>\n\n";

        // Job-specific information
        if (isset($data['bm_account_name'])) {
            $message .= "🏢 <b>BM Account:</b> {$data['bm_account_name']}\n";
        }

        if (isset($data['job_id'])) {
            $message .= "🆔 <b>Job ID:</b> {$data['job_id']}\n";
        }

        if (isset($data['pattern'])) {
            $message .= "📝 <b>Pattern:</b> {$data['pattern']}\n";
        }

        if (isset($data['total_accounts'])) {
            $message .= "🎯 <b>Total Accounts:</b> {$data['total_accounts']}\n";
        }

        if (isset($data['processed_accounts'])) {
            $message .= "✔️ <b>Processed:</b> {$data['processed_accounts']}\n";
        }

        if (isset($data['progress'])) {
            $message .= "📈 <b>Progress:</b> {$data['progress']}%\n";
        }

        if (isset($data['duration'])) {
            $message .= "⏱️ <b>Duration:</b> {$data['duration']}\n";
        }

        if (isset($data['accounts_per_minute'])) {
            $message .= "⚡ <b>Speed:</b> {$data['accounts_per_minute']}/min\n";
        }

        // Error information
        if (isset($data['error'])) {
            $message .= "\n<b>Error:</b>\n<code>{$data['error']}</code>\n";
        }

        // started_at and completed_at timestamps
        if (isset($data['started_at'])) {
            $message .= "🚦 <b>Started At:</b> {$data['started_at']}\n";
        }

        if (isset($data['completed_at'])) {
            $message .= "🏁 <b>Completed At:</b> {$data['completed_at']}\n";
        }

        if (isset($data['timestamp'])) {
            $message .= "\n🕐 {$data['timestamp']}";
        } else {
            $message .= "\n🕐 " . now()->format('Y-m-d H:i:s');
        }

        return $message;
    }

    /**
     * Test a bot connection by sending a test message
     */
    public function testBot(UserTelegramBot $bot): array
    {
        $message = "🧪 <b>Test Notification</b>\n\n";
        $message .= "This is a test message from your AdAccount Generator.\n";
        $message .= "Your Telegram bot is configured correctly! ✅\n\n";
        $message .= "🕐 " . now()->format('Y-m-d H:i:s');

        return $this->send($bot, $message);
    }
}
