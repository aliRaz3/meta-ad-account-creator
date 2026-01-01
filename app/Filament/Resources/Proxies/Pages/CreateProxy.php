<?php

namespace App\Filament\Resources\Proxies\Pages;

use App\Filament\Resources\Proxies\ProxyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateProxy extends CreateRecord
{
    protected static string $resource = ProxyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}
