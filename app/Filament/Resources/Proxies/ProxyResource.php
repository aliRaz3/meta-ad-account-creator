<?php

namespace App\Filament\Resources\Proxies;

use App\Filament\Resources\Proxies\Pages\CreateProxy;
use App\Filament\Resources\Proxies\Pages\EditProxy;
use App\Filament\Resources\Proxies\Pages\ListProxies;
use App\Filament\Resources\Proxies\Pages\ViewProxy;
use App\Filament\Resources\Proxies\Schemas\ProxyForm;
use App\Filament\Resources\Proxies\Schemas\ProxyInfolist;
use App\Filament\Resources\Proxies\Tables\ProxiesTable;
use App\Models\UserProxy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProxyResource extends Resource
{
    protected static ?string $model = UserProxy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Proxies';

    protected static ?string $modelLabel = 'Proxy';

    protected static string|\UnitEnum|null $navigationGroup  = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ProxyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProxyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProxiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProxies::route('/'),
        ];
    }
}
