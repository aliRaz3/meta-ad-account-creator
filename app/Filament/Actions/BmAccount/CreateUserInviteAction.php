<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->schema(static::schema())
            ->action(fn(BmAccount $record, array $data) => static::handle($record, $data));
    }

    protected static function schema(): array
    {
        return [
            TagsInput::make('emails')
                ->label('Email Addresses')
                ->required()
                ->placeholder('Type email and press Enter')
                ->helperText('Type an email address and press Enter to add it. You can add multiple emails.')
                ->separator(',')
                ->splitKeys(['Enter', ',', ' '])
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
                ->default('ADMIN')
                ->helperText('The role to assign to all invited users')
                ->columnSpan(12),
        ];
    }

    protected static function handle(BmAccount $record, array $data): void
    {
        $bmUpdateService = app(BMUpdateService::class);


        // Parse and validate emails from tags input
        $emails = collect(explode(',', $data['emails'] ?? []))
            ->map(fn($email) => trim($email))
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            Notification::make()
                ->title('No valid emails provided')
                ->body('Please provide at least one valid email address')
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $successful = [];
        $failed = [];

        foreach ($emails as $email) {
            try {
                $result = $bmUpdateService->createBusinessUserInvite(
                    $record->business_portfolio_id,
                    $record->access_token,
                    $email,
                    $data['role']
                );

                if ($result['success']) {
                    $successful[] = $email;
                } else {
                    $errorMessage = $bmUpdateService->formatError($result);
                    $failed[] = [
                        'email' => $email,
                        'error' => $errorMessage
                    ];
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'email' => $email,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Prepare notification body
        $totalEmails = $emails->count();
        $successCount = count($successful);
        $failedCount = count($failed);

        // Build HTML formatted body with scrollable content
        $body = '<div style="max-height: 300px; overflow-y: auto;">';

        // Statistics summary
        $body .= '<div style="margin-bottom: 12px; padding: 8px; background: rgba(0,0,0,0.05); border-radius: 4px;">';
        $body .= '<strong>Summary:</strong> ';
        $body .= "Total: {$totalEmails} | ";
        $body .= "Successful: {$successCount} | ";
        $body .= "Failed: {$failedCount}";
        $body .= '</div>';

        // Successful emails
        if ($successCount > 0) {
            $body .= '<div style="margin-bottom: 8px;">';
            $body .= '<strong style="color: #10b981;">✓ Successfully sent to:</strong>';
            $body .= '<div style="margin-top: 4px; padding-left: 8px;">';
            foreach ($successful as $email) {
                $body .= '<div style="padding: 2px 0;">• ' . htmlspecialchars($email) . '</div>';
            }
            $body .= '</div></div>';
        }

        // Failed emails
        if ($failedCount > 0) {
            $body .= '<div style="margin-top: 8px;">';
            $body .= '<strong style="color: #ef4444;">✗ Failed:</strong>';
            $body .= '<div style="margin-top: 4px; padding-left: 8px;">';
            foreach ($failed as $failure) {
                $body .= '<div style="padding: 4px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">';
                $body .= '<div style="font-weight: 500;">' . htmlspecialchars($failure['email']) . '</div>';
                $body .= '<div style="font-size: 0.875rem; color: #6b7280; margin-top: 2px;">' . htmlspecialchars($failure['error']) . '</div>';
                $body .= '</div>';
            }
            $body .= '</div></div>';
        }

        $body .= '</div>';

        // Show notification based on results
        if ($failedCount === 0) {
            Notification::make()
                ->title('All invitations sent successfully')
                ->body($body)
                ->success()
                ->persistent()
                ->send();
        } elseif ($successCount === 0) {
            Notification::make()
                ->title('All invitations failed')
                ->body($body)
                ->danger()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Invitations completed with some failures')
                ->body($body)
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
