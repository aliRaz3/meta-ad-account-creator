<?php

namespace App\Services\Meta;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdAccountService
{
    protected string $baseUrl;
    protected string $apiVersion;

    public function __construct()
    {
        $this->baseUrl = config('adaccount.meta_api_base_url');
        $this->apiVersion = config('adaccount.meta_api_version');
    }

    /**
     * Normalize ad account ID by ensuring it has the 'act_' prefix
     *
     * @param string $adAccountId
     * @return string
     */
    protected function normalizeAdAccountId(string $adAccountId): string
    {
        if (!str_starts_with($adAccountId, 'act_')) {
            return 'act_' . $adAccountId;
        }
        return $adAccountId;
    }

    /**
     * Get ad account information from Meta API
     *
     * @param string $adAccountId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getAdAccountInfo(string $adAccountId, string $accessToken): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$adAccountId}";

        try {
            Log::info("Meta API: Getting ad account info", [
                'ad_account_id' => $adAccountId,
            ]);

            $httpClient = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'MetaAdAccountManager/1.0',
                ])
                ->withoutVerifying();

            /** @var Response $response */
            $response = $httpClient->get($url, [
                'access_token' => $accessToken,
                'fields' => 'id,account_id,name,currency,timezone_id,account_status',
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("Meta API: Ad account info retrieved successfully", [
                    'ad_account_id' => $adAccountId,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $error = $data['error'] ?? [];
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Unknown error',
                'error_code' => $error['code'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error("Meta API: Exception during get ad account info", [
                'ad_account_id' => $adAccountId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update ad account name via Meta Graph API
     *
     * @param string $adAccountId
     * @param string $accessToken
     * @param string $name
     * @return array
     * @throws Exception
     */
    public function updateAdAccountName(string $adAccountId, string $accessToken, string $name): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$adAccountId}";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Updating ad account name", [
                    'ad_account_id' => $adAccountId,
                    'name' => $name,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaAdAccountManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->post($url, [
                    'access_token' => $accessToken,
                    'name' => $name,
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: Ad account name updated successfully", [
                        'ad_account_id' => $adAccountId,
                        'name' => $name,
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }

                $error = $data['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorMessage = $error['message'] ?? 'Unknown error';
                $isRetryable = $this->isRetryableError($response->status(), $errorCode);

                if (!$isRetryable || $attempt === $maxRetries) {
                    Log::error("Meta API: Failed to update ad account name", [
                        'ad_account_id' => $adAccountId,
                        'error_message' => $errorMessage,
                        'error_code' => $errorCode,
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            } catch (Exception $e) {
                $lastException = $e;
                Log::error("Meta API: Exception during update ad account name", [
                    'ad_account_id' => $adAccountId,
                    'exception' => $e->getMessage(),
                ]);

                if ($attempt === $maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            }
        }

        return [
            'success' => false,
            'error' => $lastException ? $lastException->getMessage() : 'Failed after all retries',
        ];
    }

    /**
     * Assign user to ad account via Meta Graph API
     *
     * @param string $adAccountId
     * @param string $accessToken
     * @param string $userId
     * @param array $tasks
     * @return array
     * @throws Exception
     */
    public function assignUserToAdAccount(string $adAccountId, string $accessToken, string $userId, array $tasks): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$adAccountId}/assigned_users";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Assigning user to ad account", [
                    'ad_account_id' => $adAccountId,
                    'user_id' => $userId,
                    'tasks' => $tasks,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaAdAccountManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->post($url, [
                    'access_token' => $accessToken,
                    'user' => $userId,
                    'tasks' => $tasks,
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: User assigned to ad account successfully", [
                        'ad_account_id' => $adAccountId,
                        'user_id' => $userId,
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }

                $error = $data['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorMessage = $error['message'] ?? 'Unknown error';
                $isRetryable = $this->isRetryableError($response->status(), $errorCode);

                if (!$isRetryable || $attempt === $maxRetries) {
                    Log::error("Meta API: Failed to assign user to ad account", [
                        'ad_account_id' => $adAccountId,
                        'user_id' => $userId,
                        'error_message' => $errorMessage,
                        'error_code' => $errorCode,
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            } catch (Exception $e) {
                $lastException = $e;
                Log::error("Meta API: Exception during assign user to ad account", [
                    'ad_account_id' => $adAccountId,
                    'user_id' => $userId,
                    'exception' => $e->getMessage(),
                ]);

                if ($attempt === $maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            }
        }

        return [
            'success' => false,
            'error' => $lastException ? $lastException->getMessage() : 'Failed after all retries',
        ];
    }

    /**
     * Assign user to multiple ad accounts using Meta's Batch API with concurrent processing
     * Processes up to 50 requests per batch (Meta's limit) and sends all batches in parallel
     *
     * @param array $adAccountIds Array of ad account IDs
     * @param string $accessToken
     * @param string $userId
     * @param array $tasks
     * @return array ['results' => [...], 'summary' => ['total' => int, 'success' => int, 'failed' => int]]
     */
    public function assignUserToAdAccountsBatch(array $adAccountIds, string $accessToken, string $userId, array $tasks): array
    {
        if (empty($adAccountIds)) {
            return [
                'results' => [],
                'summary' => ['total' => 0, 'success' => 0, 'failed' => 0],
            ];
        }

        // Meta's batch API supports up to 50 requests per batch
        $chunks = array_chunk($adAccountIds, 50);
        $totalBatches = count($chunks);
        
        // Initialize result variables
        $allResults = [];
        $totalSuccess = 0;
        $totalFailed = 0;

        Log::info("Meta API: Starting concurrent batch assignment", [
            'total_accounts' => count($adAccountIds),
            'total_batches' => $totalBatches,
            'accounts_per_batch' => 50,
        ]);

        try {
            // Send all batch requests concurrently using Http::pool()
            $responses = Http::pool(function ($pool) use ($chunks, $accessToken, $userId, $tasks) {
                $url = "{$this->baseUrl}/{$this->apiVersion}";
                
                foreach ($chunks as $chunkIndex => $chunk) {
                    $batch = $this->buildBatchPayload($chunk, $userId, $tasks);
                    
                    $pool->as("batch_{$chunkIndex}")
                        ->timeout(60)
                        ->withHeaders(['User-Agent' => 'MetaAdAccountManager/1.0'])
                        ->withoutVerifying()
                        ->post($url, [
                            'access_token' => $accessToken,
                            'batch' => json_encode($batch),
                        ]);
                }
            });

            // Process all responses
            foreach ($responses as $batchKey => $response) {
                $chunkIndex = (int) str_replace('batch_', '', $batchKey);
                $chunk = $chunks[$chunkIndex];

                Log::info("Meta API: Processing batch response", [
                    'batch' => $chunkIndex + 1,
                    'total_batches' => $totalBatches,
                    'accounts_in_batch' => count($chunk),
                ]);

                if ($response->failed()) {
                    Log::error("Meta API: Batch request failed", [
                        'batch' => $chunkIndex + 1,
                        'status' => $response->status(),
                    ]);

                    // Mark all accounts in this batch as failed
                    foreach ($chunk as $adAccountId) {
                        $allResults[] = [
                            'ad_account_id' => $adAccountId,
                            'success' => false,
                            'error' => 'Batch request failed: ' . $response->status(),
                        ];
                        $totalFailed++;
                    }
                    continue;
                }

                // Process successful batch response
                $batchResults = $this->processBatchResponse($response->json(), $chunk);
                
                foreach ($batchResults as $result) {
                    $allResults[] = $result;
                    if ($result['success']) {
                        $totalSuccess++;
                    } else {
                        $totalFailed++;
                    }
                }
            }
        } catch (Exception $e) {
            Log::error("Meta API: Exception during concurrent batch assignment", [
                'exception' => $e->getMessage(),
                'total_accounts' => count($adAccountIds),
            ]);

            // Mark all accounts as failed if exception occurs
            $allResults = array_map(function ($adAccountId) use ($e) {
                return [
                    'ad_account_id' => $adAccountId,
                    'success' => false,
                    'error' => 'Concurrent batch failed: ' . $e->getMessage(),
                ];
            }, $adAccountIds);

            $totalSuccess = 0;
            $totalFailed = count($adAccountIds);
        }

        Log::info("Meta API: Concurrent batch assignment completed", [
            'total_accounts' => count($adAccountIds),
            'successful' => $totalSuccess,
            'failed' => $totalFailed,
        ]);

        return [
            'results' => $allResults,
            'summary' => [
                'total' => count($adAccountIds),
                'success' => $totalSuccess,
                'failed' => $totalFailed,
            ],
        ];
    }

    /**
     * Build batch payload for Meta API
     *
     * @param array $adAccountIds
     * @param string $userId
     * @param array $tasks
     * @return array
     */
    protected function buildBatchPayload(array $adAccountIds, string $userId, array $tasks): array
    {
        $batch = [];

        foreach ($adAccountIds as $index => $adAccountId) {
            $normalizedId = $this->normalizeAdAccountId($adAccountId);
            $batch[] = [
                'method' => 'POST',
                'relative_url' => "{$normalizedId}/assigned_users",
                'body' => http_build_query([
                    'user' => $userId,
                    'tasks' => $tasks,
                ]),
                'name' => "req_{$index}",
            ];
        }

        return $batch;
    }

    /**
     * Process the response from Meta's Batch API
     *
     * @param array|null $batchResponse
     * @param array $adAccountIds
     * @return array
     */
    protected function processBatchResponse(?array $batchResponse, array $adAccountIds): array
    {
        $results = [];

        if (!is_array($batchResponse)) {
            // If response is not an array, all requests failed
            foreach ($adAccountIds as $adAccountId) {
                $results[] = [
                    'ad_account_id' => $adAccountId,
                    'success' => false,
                    'error' => 'Invalid batch response',
                ];
            }
            return $results;
        }

        foreach ($batchResponse as $index => $response) {
            $adAccountId = $adAccountIds[$index] ?? null;
            
            if (!$adAccountId) {
                continue;
            }

            $code = $response['code'] ?? 500;
            $body = isset($response['body']) ? json_decode($response['body'], true) : null;

            if ($code >= 200 && $code < 300) {
                $results[] = [
                    'ad_account_id' => $adAccountId,
                    'success' => true,
                    'data' => $body,
                ];
                
                Log::info("Meta API: Batch assignment successful", [
                    'ad_account_id' => $adAccountId,
                ]);
            } else {
                $error = $body['error'] ?? [];
                $errorMessage = $error['message'] ?? 'Unknown error';
                
                $results[] = [
                    'ad_account_id' => $adAccountId,
                    'success' => false,
                    'error' => $errorMessage,
                    'error_code' => $error['code'] ?? null,
                ];

                Log::warning("Meta API: Batch assignment failed for account", [
                    'ad_account_id' => $adAccountId,
                    'error' => $errorMessage,
                ]);
            }
        }

        return $results;
    }

    /**
     * Get assigned users for ad account from Meta API
     *
     * @param string $adAccountId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getAssignedUsers(string $adAccountId, string $accessToken): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$adAccountId}/assigned_users";
        $allUsers = [];
        $after = null;
        $totalFetched = 0;

        try {
            Log::info("Meta API: Getting assigned users", [
                'ad_account_id' => $adAccountId,
            ]);

            do {
                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaAdAccountManager/1.0',
                    ])
                    ->withoutVerifying();

                $params = [
                    'access_token' => $accessToken,
                    'fields' => 'id,name,email,tasks,role',
                ];
                if ($after) {
                    $params['after'] = $after;
                }

                /** @var Response $response */
                $response = $httpClient->get($url, $params);

                $data = $response->json();

                if (!$response->successful()) {
                    $error = $data['error'] ?? [];
                    return [
                        'success' => false,
                        'error' => $error['message'] ?? 'Unknown error',
                        'error_code' => $error['code'] ?? null,
                    ];
                }

                $pageData = $data['data'] ?? [];
                $allUsers = array_merge($allUsers, $pageData);
                $totalFetched += count($pageData);

                // Check for next page cursor
                $after = $data['paging']['cursors']['after'] ?? null;

                if ($after) {
                    Log::debug("Meta API: Fetching next page of assigned users", [
                        'ad_account_id' => $adAccountId,
                        'total_fetched' => $totalFetched,
                        'after_cursor' => $after,
                    ]);
                }
            } while ($after);

            Log::info("Meta API: Assigned users retrieved successfully", [
                'ad_account_id' => $adAccountId,
                'total_count' => $totalFetched,
            ]);

            return [
                'success' => true,
                'data' => $allUsers,
            ];
        } catch (Exception $e) {
            Log::error("Meta API: Exception during get assigned users", [
                'ad_account_id' => $adAccountId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove user from ad account via Meta Graph API
     *
     * @param string $adAccountId
     * @param string $accessToken
     * @param string $userId
     * @return array
     * @throws Exception
     */
    public function removeUserFromAdAccount(string $adAccountId, string $accessToken, string $userId): array
    {
        $adAccountId = $this->normalizeAdAccountId($adAccountId);
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$adAccountId}/assigned_users";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Removing user from ad account", [
                    'ad_account_id' => $adAccountId,
                    'user_id' => $userId,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaAdAccountManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->delete($url, [
                    'access_token' => $accessToken,
                    'user' => $userId,
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: User removed from ad account successfully", [
                        'ad_account_id' => $adAccountId,
                        'user_id' => $userId,
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }

                $error = $data['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorMessage = $error['message'] ?? 'Unknown error';
                $isRetryable = $this->isRetryableError($response->status(), $errorCode);

                if (!$isRetryable || $attempt === $maxRetries) {
                    Log::error("Meta API: Failed to remove user from ad account", [
                        'ad_account_id' => $adAccountId,
                        'user_id' => $userId,
                        'error_message' => $errorMessage,
                        'error_code' => $errorCode,
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            } catch (Exception $e) {
                $lastException = $e;
                Log::error("Meta API: Exception during remove user from ad account", [
                    'ad_account_id' => $adAccountId,
                    'user_id' => $userId,
                    'exception' => $e->getMessage(),
                ]);

                if ($attempt === $maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }
            }
        }

        return [
            'success' => false,
            'error' => $lastException ? $lastException->getMessage() : 'Failed after all retries',
        ];
    }

    /**
     * Determine if an error is retryable based on status code and error code
     *
     * @param int $statusCode
     * @param int|null $errorCode
     * @return bool
     */
    protected function isRetryableError(int $statusCode, ?int $errorCode): bool
    {
        // Retry on 5xx server errors
        if ($statusCode >= 500) {
            return true;
        }

        // Retry on specific Meta API error codes
        $retryableErrorCodes = [
            1,    // API Unknown
            2,    // API Service
            4,    // API Too Many Calls
            17,   // API User Too Many Calls
            341,  // Application limit reached
            368,  // Temporarily blocked for policies violations
        ];

        return in_array($errorCode, $retryableErrorCodes);
    }

    /**
     * Fetch all owned ad accounts from Meta API with pagination
     *
     * @param string $businessId
     * @param string $accessToken
     * @return array
     */
    public function fetchOwnedAdAccounts(string $businessId, string $accessToken): array
    {
        try {
            $allAccounts = [];
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}/owned_ad_accounts";

            Log::info("Meta API: Fetching owned ad accounts", [
                'business_id' => $businessId,
            ]);

            $httpClient = Http::timeout(60)
                ->withHeaders([
                    'User-Agent' => 'MetaAdAccountManager/1.0',
                ])
                ->withoutVerifying();

            // Initial request
            /** @var Response $response */
            $response = $httpClient->get($url, [
                'access_token' => $accessToken,
                'fields' => 'id,name,currency,account_status',
                'limit' => 2500,
            ]);

            if (!$response->successful()) {
                $data = $response->json();
                $error = $data['error'] ?? [];

                Log::error("Meta API: Failed to fetch owned ad accounts", [
                    'business_id' => $businessId,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'error' => $error['message'] ?? 'Unknown error',
                    'error_code' => $error['code'] ?? null,
                ];
            }

            // Process first page
            $data = $response->json();
            if (isset($data['data'])) {
                $allAccounts = array_merge($allAccounts, $data['data']);
            }

            // Follow pagination
            while (isset($data['paging']['next'])) {
                $nextUrl = $data['paging']['next'];

                Log::info("Meta API: Fetching next page of ad accounts", [
                    'business_id' => $businessId,
                    'total_fetched' => count($allAccounts),
                ]);

                /** @var Response $response */
                $response = $httpClient->get($nextUrl);

                if (!$response->successful()) {
                    Log::warning("Meta API: Failed to fetch next page", [
                        'business_id' => $businessId,
                        'accounts_fetched' => count($allAccounts),
                    ]);
                    break;
                }

                $data = $response->json();
                if (isset($data['data'])) {
                    $allAccounts = array_merge($allAccounts, $data['data']);
                }
            }

            Log::info("Meta API: Successfully fetched all owned ad accounts", [
                'business_id' => $businessId,
                'total_accounts' => count($allAccounts),
            ]);

            return [
                'success' => true,
                'data' => $allAccounts,
                'total' => count($allAccounts),
            ];
        } catch (Exception $e) {
            Log::error("Meta API: Exception during fetch owned ad accounts", [
                'business_id' => $businessId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
