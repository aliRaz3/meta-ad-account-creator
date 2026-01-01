<?php

namespace App\Filament\Resources\BmAccountResource\Pages;

use App\Filament\Resources\BmAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBmAccount extends EditRecord
{
    protected static string $resource = BmAccountResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If access_token is not provided (toggle was off), remove it from update
        if (!isset($data['access_token']) || empty($data['access_token'])) {
            unset($data['access_token']);
        }

        return $data;
    }

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
