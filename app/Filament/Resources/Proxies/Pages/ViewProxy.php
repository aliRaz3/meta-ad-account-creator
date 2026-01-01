<?php

namespace App\Filament\Resources\Proxies\Pages;

use App\Filament\Resources\Proxies\ProxyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProxy extends ViewRecord
{
    protected static string $resource = ProxyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
