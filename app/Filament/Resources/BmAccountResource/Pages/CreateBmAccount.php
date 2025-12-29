<?php

namespace App\Filament\Resources\BmAccountResource\Pages;

use App\Filament\Resources\BmAccountResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBmAccount extends CreateRecord
{
    protected static string $resource = BmAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
