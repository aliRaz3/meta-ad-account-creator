<?php

namespace App\Filament\Resources\BmAccountResource\Pages;

use App\Filament\Resources\BmAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBmAccount extends EditRecord
{
    protected static string $resource = BmAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
