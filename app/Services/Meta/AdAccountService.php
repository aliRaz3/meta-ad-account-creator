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
}
