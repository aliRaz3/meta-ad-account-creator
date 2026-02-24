<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\AdAccount;
use App\Models\BmAccount;
use App\Services\Meta\AdAccountService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAdAccountsAction
{
    public static function make(): Action
    {
        return Action::make('sync_ad_accounts')
            ->label('Sync Ad Accounts')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Sync Ad Accounts from Meta')
            ->modalDescription('This will fetch all ad accounts from Meta and sync them to the database. New accounts will be added, existing ones will be updated, and accounts not found in Meta will be soft-deleted.')
            ->modalSubmitActionLabel('Sync Now')
            ->action(fn(BmAccount $record) => static::handle($record));
    }

    protected static function handle(BmAccount $record): void
    {
        $adAccountService = app(AdAccountService::class);

        try {
            // Fetch ad accounts from Meta
            $result = $adAccountService->fetchOwnedAdAccounts(
                $record->business_portfolio_id,
                $record->access_token
            );

            if (!$result['success']) {
                Notification::make()
                    ->title('Failed to sync ad accounts')
                    ->body($result['error'] ?? 'Unknown error occurred')
                    ->danger()
                    ->send();
                return;
            }

            $metaAccounts = $result['data'] ?? [];

            if (empty($metaAccounts)) {
                Notification::make()
                    ->title('No ad accounts found')
                    ->body('No ad accounts were found in Meta for this business.')
                    ->warning()
                    ->send();
                return;
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
                        'status' => 'Created', // $metaAccount['account_status'] ?? null,
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

                Log::info("Ad accounts synced successfully", [
                    'bm_account_id' => $record->id,
                    'total_fetched' => count($metaAccounts),
                    'created' => $created,
                    'updated' => $updated,
                    'deleted' => $deleted,
                ]);

                Notification::make()
                    ->title('Ad accounts synced successfully')
                    ->body("Created: {$created}, Updated: {$updated}, Deleted: {$deleted}")
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error("Error syncing ad accounts", [
                'bm_account_id' => $record->id,
                'exception' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error syncing ad accounts')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
