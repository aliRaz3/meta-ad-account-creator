<?php

namespace App\Jobs;

use App\Models\AdAccount;
use App\Models\BmJob;
use App\Services\MetaApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessBmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 86300; // Max 24 hours
    public int $tries = 5;    // Max 5 attempts
    public int $backoff = 2;  // Time (in seconds) to wait before retrying a failed job

    protected BmJob $bmJob;
    protected ?\Psr\Log\LoggerInterface $jobLogger = null;

    /**
     * Create a new job instance.
     */
    public function __construct(BmJob $bmJob)
    {
        $this->bmJob = $bmJob;
    }

    /**
     * Execute the job.
     */
    public function handle(MetaApiService $metaApiService): void
    {
        $this->initializeJobLogger();

        // Reload the job to get fresh data
        $this->bmJob->refresh();

        // Check if job should be processed
        if (!in_array($this->bmJob->status, ['Pending', 'Processing'])) {
            $this->logInfo("BmJob {$this->bmJob->id}: Skipping - status is {$this->bmJob->status}");
            return;
        }

        // Update status to Processing
        $this->bmJob->update([
            'status' => 'Processing',
            'error_message' => null,
        ]);

        $this->logInfo("BmJob {$this->bmJob->id}: Started processing");

        try {
            $bmAccount = $this->bmJob->bmAccount;
            $startingNumber = $this->bmJob->starting_ad_account_no;
            $totalAccounts = $this->bmJob->total_ad_accounts;
            $pattern = $this->bmJob->pattern;
            $currency = $this->bmJob->currency;
            $timezone = $this->bmJob->time_zone;

            // Create ad accounts sequentially
            for ($i = 0; $i < $totalAccounts; $i++) {
                // Refresh job status to check for pause
                $this->bmJob->refresh();

                // Check if job has been paused
                if ($this->bmJob->status === 'Paused') {
                    $this->logInfo("BmJob {$this->bmJob->id}: Paused by user");

                    // Dispatch next pending job for this BM Account
                    BmJob::dispatchNextPendingJob($this->bmJob->bm_account_id);
                    return;
                }

                $currentNumber = $startingNumber + $i;
                $accountName = $this->generateAccountName($pattern, $currentNumber);

                // Check if ad account already exists (for resume functionality)
                $existingAccount = AdAccount::where('bm_job_id', $this->bmJob->id)
                    ->where('name', $accountName)
                    ->first();

                if ($existingAccount && $existingAccount->status === 'Created') {
                    $this->logInfo("BmJob {$this->bmJob->id}: Ad account '{$accountName}' already exists, skipping");
                    continue;
                }

                // Create new ad account record with Pending status
                if (!$existingAccount) {
                    $existingAccount = AdAccount::create([
                        'user_id' => $this->bmJob->user_id,
                        'bm_account_id' => $this->bmJob->bm_account_id,
                        'bm_job_id' => $this->bmJob->id,
                        'name' => $accountName,
                        'currency' => $currency,
                        'time_zone' => $timezone,
                        'status' => 'Pending',
                    ]);
                }

                $this->logInfo("BmJob {$this->bmJob->id}: Creating ad account '{$accountName}'");

                try {
                    // Get the user for proxy support
                    $user = $bmAccount->user;

                    // Call Meta API to create ad account (with user for proxy support)
                    // Retry only for specific transient Meta error: "Invalid parameter (Trace ID:...)".
                    $result = $this->createAdAccountWithInvalidParameterRetries(
                        $metaApiService,
                        $bmAccount->business_portfolio_id,
                        $bmAccount->access_token,
                        $accountName,
                        $currency,
                        $timezone,
                        $user
                    );

                    if ($result['success']) {
                        // Update ad account as Created
                        $existingAccount->update([
                            'status' => 'Created',
                            'ad_account_id' => $result['data']['id'] ?? null,
                            'api_response' => json_encode($result['response']),
                        ]);

                        // Increment processed count
                        $this->bmJob->increment('processed_ad_accounts');

                        $this->logInfo("BmJob {$this->bmJob->id}: Successfully created ad account '{$accountName}'");
                    } else {
                        // Mark ad account as Failed
                        $existingAccount->update([
                            'status' => 'Failed',
                            'api_response' => json_encode($result['response']),
                        ]);

                        $errorMessage = $metaApiService->formatError($result);

                        $this->logError(
                            "BmJob {$this->bmJob->id}: Failed to create ad account '{$accountName}': {$errorMessage}",
                            [
                                'bm_job_id' => $this->bmJob->id,
                                'bm_account_id' => $this->bmJob->bm_account_id,
                                'ad_account_name' => $accountName,
                                'meta_response_raw' => $result['response'] ?? null,
                            ]
                        );

                        throw new \Exception("Failed to create ad account '{$accountName}': {$errorMessage}");
                    }
                } catch (\Exception $e) {
                    // Mark ad account as Failed due to exception
                    if ($existingAccount->status !== 'Failed' || empty($existingAccount->api_response)) {
                        $existingAccount->update([
                            'status' => 'Failed',
                            'api_response' => json_encode([
                                'error' => [
                                    'message' => $e->getMessage(),
                                    'type' => 'Exception',
                                    'class' => get_class($e),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                ],
                            ]),
                        ]);
                    }

                    $this->logError(
                        "BmJob {$this->bmJob->id}: Exception creating ad account '{$accountName}': {$e->getMessage()}",
                        [
                            'bm_job_id' => $this->bmJob->id,
                            'bm_account_id' => $this->bmJob->bm_account_id,
                            'ad_account_name' => $accountName,
                            'exception_class' => get_class($e),
                            'exception_file' => $e->getFile(),
                            'exception_line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]
                    );
                    throw $e;
                }
            }

            // Job completed successfully
            if ($this->bmJob->processed_ad_accounts >= $this->bmJob->total_ad_accounts) {
                $this->bmJob->update([
                    'status' => 'Completed',
                ]);
            } else {
                throw new \Exception("Job incomplete: processed {$this->bmJob->processed_ad_accounts} of {$this->bmJob->total_ad_accounts} ad accounts.");
            }

            $this->logInfo("BmJob {$this->bmJob->id}: Completed successfully");

            // Dispatch next pending job for this BM Account
            BmJob::dispatchNextPendingJob($this->bmJob->bm_account_id);
        } catch (\Exception $e) {
            // Job failed with exception

            $this->bmJob->update([
                'status' => (str_contains($e->getMessage(), 'exceeded the number of allowed ad accounts')) ? 'Completed' : 'Failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->logError(
                "BmJob {$this->bmJob->id}: Failed with exception: {$e->getMessage()}",
                [
                    'bm_job_id' => $this->bmJob->id,
                    'bm_account_id' => $this->bmJob->bm_account_id,
                    'exception_class' => get_class($e),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw $e;
        }
    }

    protected function initializeJobLogger(): void
    {
        if ($this->jobLogger !== null) {
            return;
        }

        $date = now()->format('Y-m-d');
        $directory = storage_path('logs/bm-jobs');

        File::ensureDirectoryExists($directory);

        $filePath = "{$directory}/job-{$date}-{$this->bmJob->bm_account_id}-{$this->bmJob->id}.log";

        $this->jobLogger = Log::build([
            'driver' => 'single',
            'path' => $filePath,
            'level' => 'debug',
            'replace_placeholders' => true,
        ]);
    }

    protected function logInfo(string $message): void
    {
        $this->initializeJobLogger();
        $this->jobLogger?->info($message);
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->initializeJobLogger();
        $this->jobLogger?->error($message, $context);
    }

    protected function createAdAccountWithInvalidParameterRetries(
        MetaApiService $metaApiService,
        string $businessPortfolioId,
        string $accessToken,
        string $accountName,
        string $currency,
        string $timezone,
        mixed $user
    ): array {
        $retryDelaysInSeconds = [60, 180, 300]; // 1, 3, 5 minutes
        $attempt = 0;

        while (true) {
            try {
                $result = $metaApiService->createAdAccount(
                    $businessPortfolioId,
                    $accessToken,
                    $accountName,
                    $currency,
                    $timezone,
                    $user
                );

                if (($result['success'] ?? false) === true) {
                    return $result;
                }

                $errorMessage = $metaApiService->formatError($result);

                if (!$this->shouldRetryInvalidParameterError($errorMessage) || $attempt >= count($retryDelaysInSeconds)) {
                    return $result;
                }

                $delay = $retryDelaysInSeconds[$attempt];
                $this->logInfo(
                    "BmJob {$this->bmJob->id}: Meta returned retryable invalid parameter error for '{$accountName}'. Retrying in {$delay} seconds (attempt " . ($attempt + 1) . '/3).'
                );

                sleep($delay);
                $attempt++;
            } catch (\Exception $e) {
                if (!$this->shouldRetryInvalidParameterError($e->getMessage()) || $attempt >= count($retryDelaysInSeconds)) {
                    throw $e;
                }

                $delay = $retryDelaysInSeconds[$attempt];
                $this->logInfo(
                    "BmJob {$this->bmJob->id}: Exception with retryable invalid parameter error for '{$accountName}'. Retrying in {$delay} seconds (attempt " . ($attempt + 1) . '/3).'
                );

                sleep($delay);
                $attempt++;
            }
        }
    }

    protected function shouldRetryInvalidParameterError(string $message): bool
    {
        return str_contains($message, 'Invalid parameter (Trace ID:');
    }

    /**
     * Generate account name from pattern
     *
     * @param string|null $pattern
     * @param int $number
     * @return string
     */
    protected function generateAccountName(?string $pattern, int $number): string
    {
        if (empty($pattern)) {
            return "Account-{$number}";
        }

        return str_replace('{number}', $number, $pattern);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->initializeJobLogger();

        $this->bmJob->update([
            'status' => 'Failed',
            'error_message' => $exception->getMessage(),
        ]);

        $this->logError(
            "BmJob {$this->bmJob->id}: Job failed permanently: {$exception->getMessage()}",
            [
                'bm_job_id' => $this->bmJob->id,
                'bm_account_id' => $this->bmJob->bm_account_id,
                'exception_class' => get_class($exception),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]
        );

        // Dispatch next pending job for this BM Account
        BmJob::dispatchNextPendingJob($this->bmJob->bm_account_id);
    }
}
