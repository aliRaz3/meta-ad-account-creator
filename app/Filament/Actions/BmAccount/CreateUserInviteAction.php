<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class CreateUserInviteAction
{
    public static function make(): Action
    {
        return Action::make('create_user_invite')
            ->label('Invite User')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->modal()
            ->modalWidth('2xl')
            ->modalSubmitAction(fn ($action) => $action->color('primary'))
            ->schema(static::schema())
            ->action(fn (BmAccount $record, array $data) => static::handle($record, $data));
    }

    protected static function schema(): array
    {
        return [
            TextInput::make('email')
                ->label('Email Address')
                ->email()
                ->required()
                ->maxLength(255)
                ->helperText('The email address of the person to invite')
                ->columnSpan(12),

            Select::make('role')
                ->label('Role')
                ->required()
                ->searchable()
                ->options(function () {
                    return collect(config('adaccount.business_user_roles', []))
                        ->mapWithKeys(fn($label, $code) => [$code => $label])
                        ->toArray();
                })
                ->default('EMPLOYEE')
                ->helperText('The role to assign to the invited user')
                ->columnSpan(12),
        ];
    }

    protected static function handle(BmAccount $record, array $data): void
    {
        $bmUpdateService = app(BMUpdateService::class);

        try {
            $result = $bmUpdateService->createBusinessUserInvite(
                $record->business_portfolio_id,
                $record->access_token,
                $data['email'],
                $data['role']
            );

            if ($result['success']) {
                Notification::make()
                    ->title('User invitation sent successfully')
                    ->body("Invitation sent to {$data['email']} with role {$data['role']}")
                    ->success()
                    ->send();
            } else {
                $errorMessage = $bmUpdateService->formatError($result);
                Notification::make()
                    ->title('Failed to send user invitation')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error sending user invitation')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
