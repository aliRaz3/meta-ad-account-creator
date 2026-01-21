<?php

namespace App\Services\Meta;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BMUpdateService
{
    protected string $baseUrl;
    protected string $apiVersion;

    public function __construct()
    {
        $this->baseUrl = config('adaccount.meta_api_base_url');
        $this->apiVersion = config('adaccount.meta_api_version');
    }

    /**
     * Get business information from Meta API
     *
     * @param string $businessId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getBusinessInfo(string $businessId, string $accessToken): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Getting business info", [
                    'business_id' => $businessId,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaBusinessManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->get($url, [
                    'access_token' => $accessToken,
                    'fields' => implode(',', [
                        'id',
                        'name',
                        'vertical',
                        'timezone_id',
                        'primary_page',
                        'created_time',
                        'updated_time',
                        'verification_status',
                        'link',
                        'profile_picture_uri',
                    ]),
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: Business info retrieved successfully", [
                        'business_id' => $businessId,
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }

                // Handle error response
                $error = $data['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorMessage = $error['message'] ?? 'Unknown error';
                $errorSubcode = $error['error_subcode'] ?? null;
                $fbtraceId = $error['fbtrace_id'] ?? null;

                // Check if it's a retryable error
                $isRetryable = $this->isRetryableError($response->status(), $errorCode);

                if (!$isRetryable || $attempt === $maxRetries) {
                    Log::error("Meta API: Failed to get business info", [
                        'business_id' => $businessId,
                        'status' => $response->status(),
                        'error_code' => $errorCode,
                        'error_subcode' => $errorSubcode,
                        'error_message' => $errorMessage,
                        'fbtrace_id' => $fbtraceId,
                        'attempt' => $attempt,
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                        'error_subcode' => $errorSubcode,
                        'fbtrace_id' => $fbtraceId,
                    ];
                }

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }

            } catch (Exception $e) {
                Log::error("Meta API: Exception during get business info", [
                    'business_id' => $businessId,
                    'exception' => $e->getMessage(),
                    'attempt' => $attempt,
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
            'error' => 'Failed after all retries',
        ];
    }

    /**
     * Update business name via Meta Graph API
     *
     * @param string $businessId
     * @param string $accessToken
     * @param string $name
     * @return array
     * @throws Exception
     */
    public function updateBusinessName(string $businessId, string $accessToken, string $name): array
    {
        return $this->updateBusinessInfo($businessId, $accessToken, ['name' => $name]);
    }

    /**
     * Update business information via Meta Graph API
     *
     * @param string $businessId
     * @param string $accessToken
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function updateBusinessInfo(string $businessId, string $accessToken, array $data): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Updating business info", [
                    'business_id' => $businessId,
                    'fields' => array_keys($data),
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaBusinessManager/1.0',
                    ])
                    ->withoutVerifying();

                $postData = array_merge(['access_token' => $accessToken], $data);

                /** @var Response $response */
                $response = $httpClient->post($url, $postData);

                $responseData = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: Business info updated successfully", [
                        'business_id' => $businessId,
                        'response' => $responseData,
                    ]);

                    return [
                        'success' => true,
                        'data' => $responseData,
                    ];
                }

                // Handle error response
                $error = $responseData['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorMessage = $error['message'] ?? 'Unknown error';
                $errorSubcode = $error['error_subcode'] ?? null;
                $fbtraceId = $error['fbtrace_id'] ?? null;

                // Check if it's a retryable error
                $isRetryable = $this->isRetryableError($response->status(), $errorCode);

                if (!$isRetryable || $attempt === $maxRetries) {
                    Log::error("Meta API: Failed to update business info", [
                        'business_id' => $businessId,
                        'status' => $response->status(),
                        'error_code' => $errorCode,
                        'error_subcode' => $errorSubcode,
                        'error_message' => $errorMessage,
                        'fbtrace_id' => $fbtraceId,
                        'attempt' => $attempt,
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                        'error_subcode' => $errorSubcode,
                        'fbtrace_id' => $fbtraceId,
                    ];
                }

                // Retryable error, log and continue
                Log::warning("Meta API: Retryable error, will retry", [
                    'business_id' => $businessId,
                    'error_message' => $errorMessage,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }

            } catch (Exception $e) {
                $lastException = $e;

                Log::error("Meta API: Exception during business info update", [
                    'business_id' => $businessId,
                    'exception' => $e->getMessage(),
                    'attempt' => $attempt,
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

        // This shouldn't be reached, but just in case
        return [
            'success' => false,
            'error' => $lastException ? $lastException->getMessage() : 'Failed after all retries',
        ];
    }

    /**
     * Create business user invite link via Meta Graph API
     *
     * @param string $businessId
     * @param string $accessToken
     * @param string $email
     * @param string $role
     * @return array
     * @throws Exception
     */
    public function createBusinessUserInvite(
        string $businessId,
        string $accessToken,
        string $email,
        string $role
    ): array {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}/business_users";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Creating business user invite", [
                    'business_id' => $businessId,
                    'email' => $email,
                    'role' => $role,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaBusinessManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->post($url, [
                    'access_token' => $accessToken,
                    'email' => $email,
                    'role' => $role,
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: Business user invite created successfully", [
                        'business_id' => $businessId,
                        'email' => $email,
                        'response' => $data,
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                    ];
                }

                // Handle error response
                $error = $data['error'] ?? [];
                $errorCode = $error['code'] ?? null;
                $errorMessage = $error['message'] ?? 'Unknown error';
                $errorSubcode = $error['error_subcode'] ?? null;
                $fbtraceId = $error['fbtrace_id'] ?? null;

                // Check if it's a retryable error
                $isRetryable = $this->isRetryableError($response->status(), $errorCode);

                if (!$isRetryable || $attempt === $maxRetries) {
                    Log::error("Meta API: Failed to create business user invite", [
                        'business_id' => $businessId,
                        'email' => $email,
                        'status' => $response->status(),
                        'error_code' => $errorCode,
                        'error_subcode' => $errorSubcode,
                        'error_message' => $errorMessage,
                        'fbtrace_id' => $fbtraceId,
                        'attempt' => $attempt,
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_code' => $errorCode,
                        'error_subcode' => $errorSubcode,
                        'fbtrace_id' => $fbtraceId,
                    ];
                }

                // Retryable error, log and continue
                Log::warning("Meta API: Retryable error, will retry", [
                    'business_id' => $businessId,
                    'error_message' => $errorMessage,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }

            } catch (Exception $e) {
                $lastException = $e;

                Log::error("Meta API: Exception during business user invite creation", [
                    'business_id' => $businessId,
                    'exception' => $e->getMessage(),
                    'attempt' => $attempt,
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

        // This shouldn't be reached, but just in case
        return [
            'success' => false,
            'error' => $lastException ? $lastException->getMessage() : 'Failed after all retries',
        ];
    }

    /**
     * Get business users from Meta API
     *
     * @param string $businessId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getBusinessUsers(string $businessId, string $accessToken): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}/business_users";

        try {
            Log::info("Meta API: Getting business users", [
                'business_id' => $businessId,
            ]);

            $httpClient = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'MetaBusinessManager/1.0',
                ])
                ->withoutVerifying();

            /** @var Response $response */
            $response = $httpClient->get($url, [
                'access_token' => $accessToken,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("Meta API: Business users retrieved successfully", [
                    'business_id' => $businessId,
                    'count' => count($data['data'] ?? []),
                    'data' => $data,
                ]);

                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                ];
            }

            $error = $data['error'] ?? [];
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Unknown error',
                'error_code' => $error['code'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error("Meta API: Exception during get business users", [
                'business_id' => $businessId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get pending business users from Meta API
     *
     * @param string $businessId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getPendingUsers(string $businessId, string $accessToken): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}/pending_users";

        try {
            Log::info("Meta API: Getting pending users", [
                'business_id' => $businessId,
            ]);

            $httpClient = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'MetaBusinessManager/1.0',
                ])
                ->withoutVerifying();

            /** @var Response $response */
            $response = $httpClient->get($url, [
                'access_token' => $accessToken,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("Meta API: Pending users retrieved successfully", [
                    'business_id' => $businessId,
                    'count' => count($data['data'] ?? []),
                ]);

                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                ];
            }

            $error = $data['error'] ?? [];
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Unknown error',
                'error_code' => $error['code'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error("Meta API: Exception during get pending users", [
                'business_id' => $businessId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get system users from Meta API
     *
     * @param string $businessId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function getSystemUsers(string $businessId, string $accessToken): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}/system_users";

        try {
            Log::info("Meta API: Getting system users", [
                'business_id' => $businessId,
            ]);

            $httpClient = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'MetaBusinessManager/1.0',
                ])
                ->withoutVerifying();

            /** @var Response $response */
            $response = $httpClient->get($url, [
                'access_token' => $accessToken,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info("Meta API: System users retrieved successfully", [
                    'business_id' => $businessId,
                    'count' => count($data['data'] ?? []),
                ]);

                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                ];
            }

            $error = $data['error'] ?? [];
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Unknown error',
                'error_code' => $error['code'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error("Meta API: Exception during get system users", [
                'business_id' => $businessId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Change user role via Meta Graph API
     *
     * @param string $userId
     * @param string $accessToken
     * @param string $role
     * @return array
     * @throws Exception
     */
    public function changeUserRole(string $userId, string $accessToken, string $role): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$userId}";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Changing user role", [
                    'user_id' => $userId,
                    'role' => $role,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaBusinessManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->post($url, [
                    'access_token' => $accessToken,
                    'role' => $role,
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: User role changed successfully", [
                        'user_id' => $userId,
                        'role' => $role,
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
                    Log::error("Meta API: Failed to change user role", [
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
                Log::error("Meta API: Exception during change user role", [
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
     * Remove user from business via Meta Graph API
     *
     * @param string $userId
     * @param string $accessToken
     * @return array
     * @throws Exception
     */
    public function removeUser(string $userId, string $accessToken): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$userId}";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Removing user", [
                    'user_id' => $userId,
                    'attempt' => $attempt,
                ]);

                $httpClient = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaBusinessManager/1.0',
                    ])
                    ->withoutVerifying();

                /** @var Response $response */
                $response = $httpClient->delete($url, [
                    'access_token' => $accessToken,
                ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: User removed successfully", [
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
                    Log::error("Meta API: Failed to remove user", [
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
                Log::error("Meta API: Exception during remove user", [
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
        // Rate limit errors (retryable)
        if (in_array($errorCode, [4, 17, 341, 368])) {
            return true;
        }

        // Temporary errors (retryable)
        if (in_array($errorCode, [1, 2])) {
            return true;
        }

        // 5xx server errors (retryable)
        if ($statusCode >= 500) {
            return true;
        }

        // Network timeout or connection errors (retryable)
        if ($statusCode === 0) {
            return true;
        }

        // Rate limit status code
        if ($statusCode === 429) {
            return true;
        }

        // All other errors are not retryable
        return false;
    }

    /**
     * Format error message for display
     *
     * @param array $errorData
     * @return string
     */
    public function formatError(array $errorData): string
    {
        $code = $errorData['error_code'] ?? null;
        $subcode = $errorData['error_subcode'] ?? null;
        $message = $errorData['error'] ?? 'Unknown error';
        $fbtraceId = $errorData['fbtrace_id'] ?? null;

        // Authentication errors
        if (in_array($code, [102, 190]) || ($code >= 200 && $code <= 299)) {
            return "Authentication error: {$message}. Please check your access token.";
        }

        // Rate limit errors
        if ($code === 17) {
            return "User rate limit exceeded. Please wait before making more requests.";
        }
        if ($code === 341) {
            return "Application limit reached. Please wait and retry.";
        }
        if ($code === 368) {
            return "Temporarily blocked for policy violations. Please wait and retry.";
        }

        // Permission errors
        if (in_array($code, [3, 10, 3910, 3911])) {
            return "Permission denied. Please check your business permissions.";
        }

        // Business Manager specific errors
        if ($code === 3974) {
            return "Invalid business name. Please try a different name.";
        }
        if ($code === 3947) {
            return "Business name already exists. Please choose a different name.";
        }
        if ($code === 3973) {
            return "Invalid business name. Please choose another.";
        }

        $errorMsg = $message;
        if ($fbtraceId) {
            $errorMsg .= " (Trace ID: {$fbtraceId})";
        }

        return $errorMsg;
    }
}
