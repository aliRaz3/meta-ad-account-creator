<?php

namespace App\Filament\Actions\BmAccount;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class DownloadBmImportTemplateAction
{
    public static function make(): Action
    {
        return Action::make('download_template')
            ->label('Download Template')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info')
            ->action(fn() => static::handle());
    }

    protected static function handle()
    {
        try {
            $fileName = 'bm-import-template-' . now()->format('Y-m-d') . '.xlsx';
            $filePath = storage_path('app/temp/' . $fileName);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Writer();
            $writer->openToFile($filePath);

            // Add header row
            $headerCells = [
                Cell::fromValue('title'),
                Cell::fromValue('business_portfolio_id'),
                Cell::fromValue('access_token'),
                Cell::fromValue('new_bm_name'),
            ];
            $headerRow = new Row($headerCells);
            $writer->addRow($headerRow);

            // Add sample data row
            $sampleCells = [
                Cell::fromValue('My BM Account'),
                Cell::fromValue('123456789'),
                Cell::fromValue('EAABsbCS1...'),
                Cell::fromValue('Updated Business Name (Optional)'),
            ];
            $sampleRow = new Row($sampleCells);
            $writer->addRow($sampleRow);

            $writer->close();

            // Return the file as download
            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Template Download Failed')
                ->body('Could not generate template: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
