<?php

namespace App\Filament\Resources\BmAccountResource\Pages;

use App\Filament\Resources\BmAccountResource;
use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;

class ManageBusinessUsers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = BmAccountResource::class;

    protected string $view = 'filament.resources.bm-account-resource.pages.manage-business-users';

    protected static ?string $title = 'Business Users';

    public ?BmAccount $record = null;

    public function mount($record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string | Htmlable
    {
        return 'Business Users';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Business Users';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->getTableRecordsArray())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ADMIN' => 'success',
                        'EMPLOYEE' => 'info',
                        'DEVELOPER' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Pending' => 'warning',
                        'System' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('change_role')
                    ->label('Change Role')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (array $record): bool => $record['status'] === 'Active')
                    ->schema(function (array $record) {
                        return [
                            Forms\Components\Select::make('role')
                                ->label('Role')
                                ->options(config('adaccount.business_user_roles'))
                                ->default($record['role'])
                                ->required()
                                ->helperText('Select the new role for this user'),
                        ];
                    })
                    ->action(function (array $data, array $record): void {
                        try {
                            $bmAccount = $this->record;
                            $accessToken = $bmAccount->access_token;

                            $service = new BMUpdateService();
                            $result = $service->changeUserRole(
                                $record['id'],
                                $accessToken,
                                $data['role']
                            );

                            if ($result['success']) {
                                Notification::make()
                                    ->title('Role Changed Successfully')
                                    ->success()
                                    ->body("User role has been updated to {$data['role']}")
                                    ->send();

                                // Refresh the table
                                $this->dispatch('$refresh');
                            } else {
                                Notification::make()
                                    ->title('Failed to Change Role')
                                    ->danger()
                                    ->body($result['error'] ?? 'Unknown error occurred')
                                    ->send();
                            }
                        } catch (Exception $e) {
                            Log::error('Failed to change user role', [
                                'user_id' => $record['id'],
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('An error occurred: ' . $e->getMessage())
                                ->send();
                        }
                    })
                    ->modalHeading('Change User Role')
                    ->modalSubmitActionLabel('Change Role')
                    ->modalWidth('md'),

                Action::make('remove_user')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (array $record): bool => in_array($record['status'], ['Active', 'Pending']))
                    ->requiresConfirmation()
                    ->modalHeading('Remove User')
                    ->modalDescription(fn (array $record): string =>
                        "Are you sure you want to remove {$record['name']} ({$record['email']}) from this Business Manager?"
                    )
                    ->modalSubmitActionLabel('Yes, Remove User')
                    ->action(function (array $record): void {
                        try {
                            $bmAccount = $this->record;
                            $accessToken = $bmAccount->access_token;

                            $service = new BMUpdateService();
                            $result = $service->removeUser(
                                $record['id'],
                                $accessToken
                            );

                            if ($result['success']) {
                                Notification::make()
                                    ->title('User Removed Successfully')
                                    ->success()
                                    ->body("{$record['name']} has been removed from the Business Manager")
                                    ->send();

                                // Refresh the table
                                $this->dispatch('$refresh');
                            } else {
                                Notification::make()
                                    ->title('Failed to Remove User')
                                    ->danger()
                                    ->body($result['error'] ?? 'Unknown error occurred')
                                    ->send();
                            }
                        } catch (Exception $e) {
                            Log::error('Failed to remove user', [
                                'user_id' => $record['id'],
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Error')
                                ->danger()
                                ->body('An error occurred: ' . $e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No Users Found')
            ->emptyStateDescription('This Business Manager has no users yet.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    protected function getTableRecordsArray(): array
    {
        try {
            $bmAccount = $this->record;
            $accessToken = $bmAccount->access_token;
            $businessId = $bmAccount->business_portfolio_id;

            $service = new BMUpdateService();

            // Fetch all three types of users
            $businessUsersResult = $service->getBusinessUsers($businessId, $accessToken);
            $pendingUsersResult = $service->getPendingUsers($businessId, $accessToken);
            $systemUsersResult = $service->getSystemUsers($businessId, $accessToken);

            $allUsers = [];

            // Process business users
            if ($businessUsersResult['success']) {
                foreach ($businessUsersResult['data'] as $user) {
                    $key = $user['id'] ?? uniqid('user_', true);
                    $allUsers[$key] = [
                        'id' => $user['id'] ?? null,
                        'name' => ($user['name'] ?? 'N/A') . ' (' . ($user['id'] ?? 'N/A') . ')',
                        'email' => $user['email'] ?? 'N/A',
                        'role' => $user['role'] ?? 'N/A',
                        'status' => 'Active',
                    ];
                }
            }

            // Process pending users
            if ($pendingUsersResult['success']) {
                foreach ($pendingUsersResult['data'] as $user) {
                    $key = $user['id'] ?? uniqid('pending_', true);
                    $allUsers[$key] = [
                        'id' => $user['id'] ?? null,
                        'name' => ($user['name'] ?? 'N/A') . ' (' . ($user['id'] ?? 'N/A') . ')',
                        'email' => $user['email'] ?? $user['invited_email'] ?? 'N/A',
                        'role' => $user['role'] ?? 'N/A',
                        'status' => 'Pending',
                    ];
                }
            }

            // Process system users
            if ($systemUsersResult['success']) {
                foreach ($systemUsersResult['data'] as $user) {
                    $key = $user['id'] ?? uniqid('system_', true);
                    $allUsers[$key] = [
                        'id' => $user['id'] ?? null,
                        'name' => $user['name'] ?? 'N/A',
                        'email' => $user['email'] ?? 'System User',
                        'role' => $user['role'] ?? 'SYSTEM',
                        'status' => 'System',
                    ];
                }
            }

            return $allUsers;

        } catch (Exception $e) {
            Log::error('Failed to fetch business users', [
                'bm_account_id' => $bmAccount->id ?? null,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Error Loading Users')
                ->danger()
                ->body('Failed to load users from Meta API: ' . $e->getMessage())
                ->send();

            return [];
        }
    }
}
