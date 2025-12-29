<?php

namespace App\Filament\Resources\BmJobResource\Pages;

use App\Filament\Resources\BmJobResource;
use App\Jobs\ProcessBmJob;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBmJob extends CreateRecord
{
    protected static string $resource = BmJobResource::class;

    public function mount(): void
    {
        parent::mount();

        // Pre-fill bm_account_id if provided in URL
        if (request()->has('bm_account_id')) {
            $this->form->fill([
                'bm_account_id' => request()->get('bm_account_id'),
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] = 'Pending';
        $data['processed_ad_accounts'] = 0;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Try to dispatch if no other job is processing for this BM Account
        if (!$this->record->dispatchIfAvailable()) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Job Queued')
                ->body('Another job is currently processing for this BM Account. This job will start when it finishes.')
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
