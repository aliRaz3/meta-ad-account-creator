<?php

namespace App\Filament\Resources;

use App\Filament\Actions\BmAccount\CreateBmJobAction;
use App\Filament\Actions\BmAccount\CreateUserInviteAction;
use App\Filament\Actions\BmAccount\ManageBusinessUsersAction;
use App\Filament\Actions\BmAccount\UpdateBusinessInfoAction;
use App\Filament\Actions\BmAccount\UpdateBusinessNameAction;
use App\Filament\Resources\BmAccountResource\Pages;
use App\Filament\Resources\BmAccountResource\RelationManagers;
use App\Models\BmAccount;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BmAccountResource extends Resource
{
    protected static ?string $model = BmAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'BM Accounts';

    protected static ?string $modelLabel = 'BM Account';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->label('Account Title')
                    ->columnSpan(6)
                    ->helperText('A friendly name for this BM account'),

                TextInput::make('business_portfolio_id')
                    ->required()
                    ->maxLength(255)
                    ->label('Business Portfolio ID')
                    ->columnSpan(6)
                    ->helperText('The Meta Business Portfolio ID')
                    ->unique(ignoreRecord: true),

                Toggle::make('update_access_token')
                    ->label('Update Access Token')
                    ->helperText('Enable to update the access token')
                    ->default(fn($record) => $record === null) // Default ON for create, OFF for edit
                    ->live()
                    ->dehydrated(false)
                    ->columnSpan(12),

                Textarea::make('access_token')
                    ->label('Access Token')
                    ->helperText('Meta API access token (will be encrypted)')
                    ->rows(3)
                    ->columnSpan(12)
                    ->required(fn($get) => $get('update_access_token') === true)
                    ->visible(fn($get) => $get('update_access_token') === true)
                    ->dehydrated(fn($get) => $get('update_access_token') === true)
            ])
            ->columns(12);
    }

    /**
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->label('Title'),

                TextColumn::make('business_portfolio_id')
                    ->searchable()
                    ->sortable()
                    ->label('Business Portfolio ID')
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->copyMessageDuration(1500),

                TextColumn::make('bm_jobs_count')
                    ->counts('bmJobs')
                    ->label('Total Jobs')
                    ->sortable(),

                TextColumn::make('ad_accounts_count')
                    ->counts('adAccounts')
                    ->label('Ad Accounts')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn () => Auth::user()->isAdmin()),
            ])
            ->recordActions([
                ActionGroup::make([
                    UpdateBusinessNameAction::make(),
                    UpdateBusinessInfoAction::make(),
                    CreateUserInviteAction::make(),
                    ManageBusinessUsersAction::make(),
                    CreateBmJobAction::make(),
                    ViewAction::make(),
                    EditAction::make()
                        ->modal()
                        ->modalWidth('2xl'),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make()
                        ->visible(fn () => Auth::user()->isAdmin()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->isAdmin()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BmJobsRelationManager::class,
            RelationManagers\AdAccountsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBmAccounts::route('/'),
            'view' => Pages\ViewBmAccount::route('/{record}'),
            'business-users' => Pages\ManageBusinessUsers::route('/{record}/business-users'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id())
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
    }
}
