<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Instructions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?string $navigationLabel = 'Help & Instructions';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.instructions';

    public static function getNavigationLabel(): string
    {
        return 'Help & Instructions';
    }

    public function getTitle(): string
    {
        return 'Help & Instructions';
    }
}
