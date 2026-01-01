<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingsResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class ManageSettings extends EditRecord
{
    protected static string $resource = SettingsResource::class;

    protected static bool $canCreateAnother = false;

    protected function resolveRecord(int | string $record): \Illuminate\Database\Eloquent\Model
    {
        // Always return the authenticated user's settings (create if doesn't exist)
        return auth()->user()->getOrCreateSettings();
    }

    public function mount(int | string $record = null): void
    {
        // Get or create settings for the current user
        $settings = auth()->user()->getOrCreateSettings();

        // Call parent mount with the correct record ID
        parent::mount($settings->id);
    }

    public function getBreadcrumbs(): array
    {
        // Return simple breadcrumbs without links to avoid URL generation issues
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->submit(null)
                ->keyBindings(['mod+s']),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        // Stay on the same page after saving
        return null;
    }

    public function getBreadcrumb(): string
    {
        return 'Settings';
    }
}
