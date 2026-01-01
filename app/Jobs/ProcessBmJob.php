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
use Illuminate\Support\Facades\Log;

class ProcessBmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 18000; // 5 hours timeout
    public int $tries = 5;    // Max 5 attempts

    protected BmJob $bmJob;

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
        // Reload the job to get fresh data
        $this->bmJob->refresh();

        // Check if job should be processed
        if (!in_array($this->bmJob->status, ['Pending', 'Processing'])) {
            Log::info("BmJob {$this->bmJob->id}: Skipping - status is {$this->bmJob->status}");
            return;
        }

        // Update status to Processing
        $this->bmJob->update([
            'status' => 'Processing',
            'error_message' => null,
        ]);

        Log::info("BmJob {$this->bmJob->id}: Started processing");

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
                    Log::info("BmJob {$this->bmJob->id}: Paused by user");

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
                    Log::info("BmJob {$this->bmJob->id}: Ad account '{$accountName}' already exists, skipping");
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

                Log::info("BmJob {$this->bmJob->id}: Creating ad account '{$accountName}'");

                try {
                    // Get the user for proxy support
                    $user = $bmAccount->user;

                    // Call Meta API to create ad account (with user for proxy support)
                    $result = $metaApiService->createAdAccount(
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

                        Log::info("BmJob {$this->bmJob->id}: Successfully created ad account '{$accountName}'");
                    } else {
                        // Mark ad account as Failed
                        $existingAccount->update([
                            'status' => 'Failed',
                            'api_response' => json_encode($result['response']),
                        ]);

                        $errorMessage = $metaApiService->formatError($result);
                        Log::error("BmJob {$this->bmJob->id}: Failed to create ad account '{$accountName}': {$errorMessage}");

                        throw new \Exception("Failed to create ad account '{$accountName}': {$errorMessage}");
                    }
                } catch (\Exception $e) {
                    // Mark ad account as Failed due to exception
                    $existingAccount->update([
                        'status' => 'Failed',
                        'api_response' => json_encode([
                            'error' => [
                                'message' => $e->getMessage(),
                                'type' => 'Exception',
                            ],
                        ]),
                    ]);

                    Log::error("BmJob {$this->bmJob->id}: Exception creating ad account '{$accountName}': {$e->getMessage()}");
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

            Log::info("BmJob {$this->bmJob->id}: Completed successfully");

            // Dispatch next pending job for this BM Account
            BmJob::dispatchNextPendingJob($this->bmJob->bm_account_id);

        } catch (\Exception $e) {
            // Job failed with exception

            $this->bmJob->update([
                'status' => (str_contains($e->getMessage(), 'exceeded the number of allowed ad accounts')) ? 'Completed' : 'Failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("BmJob {$this->bmJob->id}: Failed with exception: {$e->getMessage()}");
            throw $e;
        }
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
        $this->bmJob->update([
            'status' => 'Failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error("BmJob {$this->bmJob->id}: Job failed permanently: {$exception->getMessage()}");

        // Dispatch next pending job for this BM Account
        BmJob::dispatchNextPendingJob($this->bmJob->bm_account_id);
    }
}
