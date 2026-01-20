<?php

namespace App\Filament\Actions\BmAccount;

use App\Filament\Resources\BmAccountResource;
use App\Models\BmAccount;
use Filament\Actions\Action;

class ManageBusinessUsersAction
{
    public static function make(): Action
    {
        return Action::make('manage_users')
            ->label('Manage Business Users')
            ->icon('heroicon-o-user-group')
            ->color('info')
            ->url(fn (BmAccount $record): string => BmAccountResource::getUrl('business-users', ['record' => $record]));
    }
}
