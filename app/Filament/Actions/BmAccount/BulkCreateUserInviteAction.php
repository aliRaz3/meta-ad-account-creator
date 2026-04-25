<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Concurrency;

class BulkCreateUserInviteAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('bulk_create_user_invite')
            ->label('Invite User')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->modal()
            ->modalWidth('2xl')
            ->modalSubmitAction(fn($action) => $action->color('primary'))
            ->schema(static::schema())
            ->action(fn(Collection $records, array $data) => static::handle($records, $data));
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

            Radio::make('processing_mode')
                ->label('Processing Mode')
                ->options([
                    'parallel'     => 'Parallel — send to all BMs simultaneously',
                    'sequential'   => 'Sequential — send to BMs one by one',
                ])
                ->default('parallel')
                ->live()
                ->columnSpan(12),

            TextInput::make('delay_seconds')
                ->label('Delay Between BMs (seconds)')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(300)
                ->default(2)
                ->helperText('Wait this many seconds before sending to the next BM')
                ->visible(fn($get) => $get('processing_mode') === 'sequential')
                ->required(fn($get) => $get('processing_mode') === 'sequential')
                ->columnSpan(12),
        ];
    }

    protected static function handle(Collection $records, array $data): void
    {
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

        $role           = $data['role'];
        $isParallel     = ($data['processing_mode'] ?? 'parallel') === 'parallel';
        $delaySeconds   = (int) ($data['delay_seconds'] ?? 0);

        if ($isParallel) {
            $bmResults = Concurrency::run(
                $records->map(function (BmAccount $record) use ($emails, $role) {
                    return function () use ($record, $emails, $role) {
                        return static::inviteEmails($record, $emails, $role);
                    };
                })->toArray()
            );
        } else {
            $bmResults = [];
            foreach ($records as $index => $record) {
                if ($index > 0 && $delaySeconds > 0) {
                    sleep($delaySeconds);
                }
                $bmResults[] = static::inviteEmails($record, $emails, $role);
            }
        }

        static::sendNotification($emails->count(), $bmResults);
    }

    protected static function inviteEmails(BmAccount $record, \Illuminate\Support\Collection $emails, string $role): array
    {
        $bmUpdateService = app(BMUpdateService::class);
        $successful = [];
        $failed = [];

        foreach ($emails as $email) {
            try {
                $result = $bmUpdateService->createBusinessUserInvite(
                    $record->business_portfolio_id,
                    $record->access_token,
                    $email,
                    $role
                );

                if ($result['success']) {
                    $successful[] = $email;
                } else {
                    $failed[] = [
                        'email' => $email,
                        'error' => $bmUpdateService->formatError($result),
                    ];
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'bm'         => $record->title,
            'successful' => $successful,
            'failed'     => $failed,
        ];
    }

    protected static function sendNotification(int $totalEmails, array $bmResults): void
    {
        $totalBms       = count($bmResults);
        $grandSuccess   = 0;
        $grandFailed    = 0;
        $allSucceeded   = [];  // [bm => [emails]]
        $allFailed      = [];  // [bm => [[email, error]]]

        foreach ($bmResults as $result) {
            $grandSuccess += count($result['successful']);
            $grandFailed  += count($result['failed']);
            if ($result['successful']) {
                $allSucceeded[$result['bm']] = $result['successful'];
            }
            if ($result['failed']) {
                $allFailed[$result['bm']] = $result['failed'];
            }
        }

        $body  = '<div style="max-height: 350px; overflow-y: auto;">';

        // Summary bar
        $body .= '<div style="margin-bottom: 12px; padding: 8px; background: rgba(0,0,0,0.05); border-radius: 4px;">';
        $body .= '<strong>Summary:</strong> ';
        $body .= "BMs: {$totalBms} | Emails per BM: {$totalEmails} | ";
        $body .= "Invites sent: {$grandSuccess} | Failed: {$grandFailed}";
        $body .= '</div>';

        // Successful per BM
        if ($allSucceeded) {
            $body .= '<div style="margin-bottom: 8px;">';
            $body .= '<strong style="color: #10b981;">✓ Successfully sent:</strong>';
            foreach ($allSucceeded as $bm => $emails) {
                $body .= '<div style="margin-top: 6px; padding-left: 8px;">';
                $body .= '<div style="font-weight: 600;">' . htmlspecialchars($bm) . '</div>';
                foreach ($emails as $email) {
                    $body .= '<div style="padding: 2px 0; padding-left: 8px;">• ' . htmlspecialchars($email) . '</div>';
                }
                $body .= '</div>';
            }
            $body .= '</div>';
        }

        // Failed per BM
        if ($allFailed) {
            $body .= '<div style="margin-top: 8px;">';
            $body .= '<strong style="color: #ef4444;">✗ Failed:</strong>';
            foreach ($allFailed as $bm => $failures) {
                $body .= '<div style="margin-top: 6px; padding-left: 8px;">';
                $body .= '<div style="font-weight: 600;">' . htmlspecialchars($bm) . '</div>';
                foreach ($failures as $failure) {
                    $body .= '<div style="padding: 4px 0 4px 8px; border-bottom: 1px solid rgba(0,0,0,0.05);">';
                    $body .= '<div style="font-weight: 500;">' . htmlspecialchars($failure['email']) . '</div>';
                    $body .= '<div style="font-size: 0.875rem; color: #6b7280; margin-top: 2px;">' . htmlspecialchars($failure['error']) . '</div>';
                    $body .= '</div>';
                }
                $body .= '</div>';
            }
            $body .= '</div>';
        }

        $body .= '</div>';

        if ($grandFailed === 0) {
            Notification::make()
                ->title('All invitations sent successfully')
                ->body($body)
                ->success()
                ->persistent()
                ->send();
        } elseif ($grandSuccess === 0) {
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
