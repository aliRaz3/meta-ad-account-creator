<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\AdAccount;
use App\Models\BmAccount;
use App\Services\Meta\AdAccountService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkSyncAdAccountsAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('bulk_sync_ad_accounts')
            ->label('Sync Ad Accounts')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Sync Ad Accounts from Meta')
            ->modalDescription('This will fetch all ad accounts from Meta for each selected BM account and sync them to the database. New accounts will be added, existing ones will be updated, and accounts not found in Meta will be soft-deleted.')
            ->modalSubmitActionLabel('Sync All')
            ->action(fn(Collection $records) => static::handle($records));
    }

    protected static function handle(Collection $records): void
    {
        $adAccountService = app(AdAccountService::class);
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalDeleted = 0;
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($records as $record) {
            try {
                // Fetch ad accounts from Meta
                $result = $adAccountService->fetchOwnedAdAccounts(
                    $record->business_portfolio_id,
                    $record->access_token
                );

                if (!$result['success']) {
                    $failedCount++;
                    $errors[] = "{$record->title}: " . ($result['error'] ?? 'Unknown error');
                    continue;
                }

                $metaAccounts = $result['data'] ?? [];

                if (empty($metaAccounts)) {
                    Log::info("No ad accounts found for BM account", [
                        'bm_account_id' => $record->id,
                        'title' => $record->title,
                    ]);
                    $successCount++;
                    continue;
                }

                DB::beginTransaction();

                try {
                    $metaAccountIds = [];
                    $created = 0;
                    $updated = 0;

                    // Load all existing accounts for this BM account to avoid N+1 queries
                    $existingAccountsMap = [];
                    AdAccount::withTrashed()
                        ->where('user_id', $record->user_id)
                        ->where('bm_account_id', $record->id)
                        ->chunkById(500, function ($accounts) use (&$existingAccountsMap) {
                            foreach ($accounts as $account) {
                                $existingAccountsMap[$account->ad_account_id] = $account;
                            }
                        });

                    foreach ($metaAccounts as $metaAccount) {
                        // Extract account ID without 'act_' prefix
                        $accountId = $metaAccount['id'];
                        if (str_starts_with($accountId, 'act_')) {
                            $accountId = substr($accountId, 4);
                        }

                        $metaAccountIds[] = $accountId;

                        // Check if account already exists using the map
                        $existingAccount = $existingAccountsMap[$accountId] ?? null;

                        $accountData = [
                            'user_id' => $record->user_id,
                            'bm_account_id' => $record->id,
                            'bm_job_id' => null, // Set job_id to null for synced accounts
                            'ad_account_id' => $accountId,
                            'name' => $metaAccount['name'] ?? null,
                            'currency' => $metaAccount['currency'] ?? null,
                            'status' => 'Created' //$metaAccount['account_status'] ?? null,
                        ];

                        if ($existingAccount) {
                            // Update existing account
                            $existingAccount->update($accountData);

                            // Restore if soft-deleted
                            if ($existingAccount->trashed()) {
                                $existingAccount->restore();
                            }

                            $updated++;
                        } else {
                            // Create new account
                            AdAccount::create($accountData);
                            $created++;
                        }
                    }

                    // Soft-delete accounts that are not in Meta anymore
                    $deleted = AdAccount::where('user_id', $record->user_id)
                        ->where('bm_account_id', $record->id)
                        ->whereNotIn('ad_account_id', $metaAccountIds)
                        ->delete();

                    DB::commit();

                    $totalCreated += $created;
                    $totalUpdated += $updated;
                    $totalDeleted += $deleted;
                    $successCount++;

                    Log::info("Ad accounts synced successfully for BM account", [
                        'bm_account_id' => $record->id,
                        'title' => $record->title,
                        'total_fetched' => count($metaAccounts),
                        'created' => $created,
                        'updated' => $updated,
                        'deleted' => $deleted,
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "{$record->title}: {$e->getMessage()}";

                Log::error("Error syncing ad accounts for BM account", [
                    'bm_account_id' => $record->id,
                    'title' => $record->title,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Send summary notification
        if ($successCount > 0 && $failedCount === 0) {
            Notification::make()
                ->title('All ad accounts synced successfully')
                ->body("Synced {$successCount} BM account(s). Created: {$totalCreated}, Updated: {$totalUpdated}, Deleted: {$totalDeleted}")
                ->success()
                ->send();
        } elseif ($successCount > 0 && $failedCount > 0) {
            $errorMessage = implode("\n", array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $errorMessage .= "\n... and " . (count($errors) - 3) . " more error(s)";
            }

            Notification::make()
                ->title('Partial sync completed')
                ->body("Success: {$successCount}, Failed: {$failedCount}\n\nCreated: {$totalCreated}, Updated: {$totalUpdated}, Deleted: {$totalDeleted}\n\nErrors:\n{$errorMessage}")
                ->warning()
                ->send();
        } else {
            $errorMessage = implode("\n", array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $errorMessage .= "\n... and " . (count($errors) - 3) . " more error(s)";
            }

            Notification::make()
                ->title('Failed to sync ad accounts')
                ->body($errorMessage)
                ->danger()
                ->send();
        }
    }
}
