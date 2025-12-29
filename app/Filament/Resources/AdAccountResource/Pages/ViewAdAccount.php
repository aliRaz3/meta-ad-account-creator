<?php

namespace App\Filament\Resources\AdAccountResource\Pages;

use App\Filament\Resources\AdAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAdAccount extends ViewRecord
{
    protected static string $resource = AdAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_job')
                ->label('View Job')
                ->icon('heroicon-o-eye')
                ->url(fn() => route('filament.admin.resources.bm-jobs.view', ['record' => $this->record->bm_job_id]))
                ->color('primary'),
        ];
    }
}
