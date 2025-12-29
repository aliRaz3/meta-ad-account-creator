<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaApiService
{
    protected string $baseUrl;
    protected string $apiVersion;

    public function __construct()
    {
        $this->baseUrl = config('adaccount.meta_api_base_url');
        $this->apiVersion = config('adaccount.meta_api_version');
    }

    /**
     * Create an ad account via Meta Graph API
     *
     * @param string $businessId
     * @param string $accessToken
     * @param string $name
     * @param string $currency
     * @param string $timezone
     * @return array
     * @throws Exception
     */
    public function createAdAccount(
        string $businessId,
        string $accessToken,
        string $name,
        string $currency,
        string $timezone
    ): array {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$businessId}/adaccount";

        $maxRetries = config('adaccount.retry_attempts', 3);
        $retryDelay = config('adaccount.retry_delay', 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info("Meta API: Creating ad account", [
                    'business_id' => $businessId,
                    'name' => $name,
                    'attempt' => $attempt,
                ]);

                /** @var Response $response */
                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'MetaAdAccountCreator/1.0',
                    ])
                    ->post($url, [
                        'access_token' => $accessToken,
                        'name' => $name,
                        'currency' => $currency,
                        'timezone_id' => $timezone,
                        'end_advertiser' => 'NONE',
                        'media_agency' => 'NONE',
                        'partner' => 'NONE'
                    ]);

                $data = $response->json();

                if ($response->successful()) {
                    Log::info("Meta API: Ad account created successfully", [
                        'business_id' => $businessId,
                        'name' => $name,
                        'response' => $data,
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                        'response' => $data,
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
                    Log::error("Meta API: Failed to create ad account", [
                        'business_id' => $businessId,
                        'name' => $name,
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
                        'response' => $data,
                    ];
                }

                // Retryable error, log and continue
                Log::warning("Meta API: Retryable error, will retry", [
                    'business_id' => $businessId,
                    'name' => $name,
                    'error_message' => $errorMessage,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                ]);

                if ($retryDelay > 0) {
                    sleep($retryDelay);
                }

            } catch (Exception $e) {
                $lastException = $e;

                Log::error("Meta API: Exception during ad account creation", [
                    'business_id' => $businessId,
                    'name' => $name,
                    'exception' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt === $maxRetries) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'response' => [
                            'error' => [
                                'message' => $e->getMessage(),
                                'type' => 'Exception',
                            ],
                        ],
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
            'response' => [
                'error' => [
                    'message' => 'Failed after all retries',
                ],
            ],
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
        if (in_array($code, [3, 10])) {
            return "Permission denied. Please check your app permissions and capabilities.";
        }

        $errorMsg = $message;
        if ($fbtraceId) {
            $errorMsg .= " (Trace ID: {$fbtraceId})";
        }

        return $errorMsg;
    }
}
