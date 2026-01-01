<?php

namespace App\Filament\Resources\Proxies\Pages;

use App\Filament\Resources\Proxies\ProxyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListProxies extends ListRecords
{
    protected static string $resource = ProxyResource::class;

    protected function getHeaderActions(): array
    {
        $settings = Auth::user()->getOrCreateSettings();
        $proxyEnabled = $settings->proxy_enabled;

        return [
            Action::make('proxy_settings')
                ->label('Proxy Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->form([
                    Toggle::make('proxy_enabled')
                        ->label('Enable Proxies')
                        ->helperText('Use proxies when making Meta API requests.')
                        ->default(fn () => Auth::user()->getOrCreateSettings()->proxy_enabled)
                        ->live(),

                    Select::make('proxy_rotation_type')
                        ->label('Proxy Rotation Type')
                        ->options([
                            'round-robin' => 'Round Robin - Cycle through proxies sequentially',
                            'random' => 'Random - Randomly select a proxy',
                            'sequential' => 'Sequential - Use least recently used proxy',
                        ])
                        ->native(false)
                        ->required()
                        ->default(fn () => Auth::user()->getOrCreateSettings()->proxy_rotation_type)
                        ->visible(fn ($get) => $get('proxy_enabled')),
                ])
                ->fillForm(fn () => Auth::user()->getOrCreateSettings()->toArray())
                ->action(function (array $data) {
                    Auth::user()->getOrCreateSettings()->update($data);
                    Notification::make()
                        ->title('Proxy settings updated')
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();
                    return $data;
                }),
        ];
    }
}
