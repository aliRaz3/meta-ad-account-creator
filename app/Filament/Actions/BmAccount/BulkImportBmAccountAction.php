<?php

namespace App\Filament\Actions\BmAccount;

use App\Models\BmAccount;
use App\Services\Meta\BMUpdateService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;

class BulkImportBmAccountAction
{
    public static function make(): Action
    {
        return Action::make('bulk_import')
            ->label('Bulk Import')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->modal()
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Import')
            ->schema(static::schema())
            ->action(fn(array $data) => static::handle($data));
    }

    protected static function schema(): array
    {
        return [
            TextEntry::make('instructions')
                ->label('Import Instructions')
                ->state(view('filament.components.bulk-import-instructions'))
                ->columnSpanFull(),

            FileUpload::make('file')
                ->label('Excel File')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->maxSize(5120) // 5MB
                ->required()
                ->helperText('Upload an Excel file (.xlsx). Maximum size: 5MB')
                ->disk('local')
                ->directory('imports')
                ->columnSpanFull(),
        ];
    }

    protected static function handle(array $data): void
    {
        try {
            $filePath = Storage::disk('local')->path($data['file']);

            // Determine file type and create appropriate reader
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($extension !== 'xlsx') {
                Storage::disk('local')->delete($data['file']);
                Notification::make()
                    ->title('Invalid file format')
                    ->body('Please upload a valid Excel file (.xlsx)')
                    ->danger()
                    ->send();
                return;
            }

            $reader = new XLSXReader();
            $reader->open($filePath);

            $results = [
                'total' => 0,
                'success' => 0,
                'errors' => [],
                'skipped' => 0,
                'name_update_failed' => 0,
                'name_update_errors' => [],
            ];

            $isFirstRow = true;
            $headers = [];
            $rowNumber = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = $row->getCells();
                    $rowData = [];

                    foreach ($cells as $cell) {
                        $rowData[] = $cell->getValue();
                    }

                    // First row is header
                    if ($isFirstRow) {
                        $headers = static::normalizeHeaders($rowData);
                        $isFirstRow = false;

                        // Validate headers
                        $requiredHeaders = ['title', 'business_portfolio_id', 'access_token'];
                        $missingHeaders = array_diff($requiredHeaders, $headers);

                        if (!empty($missingHeaders)) {
                            Notification::make()
                                ->title('Invalid Excel Format')
                                ->body('Missing required columns: ' . implode(', ', $missingHeaders) . '. Available columns: ' . implode(', ', $headers))
                                ->danger()
                                ->duration(10000)
                                ->send();
                            $reader->close();
                            Storage::disk('local')->delete($data['file']);
                            return;
                        }

                        continue;
                    }

                    // Skip empty rows
                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    $results['total']++;

                    // Map row data to headers
                    $record = [];
                    foreach ($headers as $index => $header) {
                        $record[$header] = $rowData[$index] ?? null;
                    }

                    // Process this row
                    $rowResult = static::processRow($record, $rowNumber);

                    if ($rowResult['success']) {
                        $results['success']++;

                        // Track business name update failures
                        if (isset($rowResult['name_update_failed']) && $rowResult['name_update_failed']) {
                            $results['name_update_failed']++;
                            $results['name_update_errors'][] = "Row {$rowNumber}: " . $rowResult['name_update_error'];
                        }
                    } else {
                        $results['errors'][] = "Row {$rowNumber}: " . $rowResult['message'];
                        $results['skipped']++;
                    }
                }
            }

            $reader->close();

            // Clean up uploaded file
            Storage::disk('local')->delete($data['file']);

            // Show results notification
            static::showResults($results);
        } catch (IOException $e) {
            Storage::disk('local')->delete($data['file']);
            Notification::make()
                ->title('File Error')
                ->body('Could not read the uploaded file: ' . $e->getMessage())
                ->danger()
                ->send();
        } catch (UnsupportedTypeException $e) {
            Storage::disk('local')->delete($data['file']);
            Notification::make()
                ->title('Unsupported File Type')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Storage::disk('local')->delete($data['file']);
            Notification::make()
                ->title('Import Error')
                ->body('An unexpected error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function normalizeHeaders(array $rawHeaders): array
    {
        return array_map(function ($header) {
            // Convert to lowercase and replace spaces with underscores
            $normalized = strtolower(trim($header));
            $normalized = str_replace([' ', '-'], '_', $normalized);
            return $normalized;
        }, $rawHeaders);
    }

    protected static function processRow(array $record, int $rowNumber): array
    {
        // Validate the record
        $validator = Validator::make($record, [
            'title' => 'required|string|max:255',
            'business_portfolio_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bm_accounts', 'business_portfolio_id')
                    ->where('user_id', Auth::id())
                    ->whereNull('deleted_at'),
            ],
            'access_token' => 'required|string',
            'new_bm_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return [
                'success' => false,
                'message' => implode(', ', $errors),
            ];
        }

        try {
            // Create BM Account
            $bmAccount = BmAccount::create([
                'user_id' => Auth::id(),
                'title' => trim($record['title']),
                'business_portfolio_id' => trim($record['business_portfolio_id']),
                'access_token' => trim($record['access_token']),
            ]);

            // If new_bm_name is provided, update the business name via API
            $nameUpdateFailed = false;
            $nameUpdateError = '';

            if (!empty($record['new_bm_name'])) {
                $bmUpdateService = app(BMUpdateService::class);

                try {
                    $result = $bmUpdateService->updateBusinessName(
                        $bmAccount->business_portfolio_id,
                        $bmAccount->access_token,
                        trim($record['new_bm_name'])
                    );

                    if ($result['success']) {
                        // Update local title to match the new name
                        $bmAccount->update(['title' => trim($record['new_bm_name'])]);
                    } else {
                        $nameUpdateFailed = true;
                        $nameUpdateError = 'API returned error: ' . ($result['error'] ?? 'Unknown error');

                        // Log the error but don't fail the import
                        Log::warning("Failed to update business name for Row {$rowNumber}", [
                            'business_portfolio_id' => $bmAccount->business_portfolio_id,
                            'error' => $result,
                        ]);
                    }
                } catch (\Exception $e) {
                    $nameUpdateFailed = true;
                    $nameUpdateError = 'Exception: ' . $e->getMessage();

                    // Log the error but don't fail the import
                    Log::warning("Exception updating business name for Row {$rowNumber}", [
                        'business_portfolio_id' => $bmAccount->business_portfolio_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'record' => $bmAccount,
                'name_update_failed' => $nameUpdateFailed,
                'name_update_error' => $nameUpdateError,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected static function showResults(array $results): void
    {
        $title = "Import Complete";

        // Build HTML formatted body for better readability with scrollable content
        $body = '<div style="font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; padding-right: 8px;">';

        $body .= '<div style="margin-bottom: 12px; font-weight: bold;">';
        $body .= "✓ Successfully imported: {$results['success']} / {$results['total']} records";
        $body .= '</div>';

        if ($results['skipped'] > 0) {
            $body .= '<div style="margin-bottom: 12px; color: #dc2626;">';
            $body .= "✗ Skipped due to errors: {$results['skipped']}";
            $body .= '</div>';
        }

        if ($results['name_update_failed'] > 0) {
            $body .= '<div style="margin-bottom: 12px; color: #f59e0b;">';
            $body .= "⚠ Business name update failed: {$results['name_update_failed']} record(s)";
            $body .= '</div>';
        }

        if (!empty($results['errors'])) {
            $body .= '<div style="margin-top: 16px; margin-bottom: 8px; font-weight: bold; color: #dc2626;">';
            $body .= 'Errors (you can retry these rows):';
            $body .= '</div>';
            $body .= '<div style="margin-left: 12px; font-size: 0.875rem;">';
            foreach ($results['errors'] as $error) {
                $body .= '• ' . htmlspecialchars($error) . '<br>';
            }
            $body .= '</div>';
        }

        if (!empty($results['name_update_errors'])) {
            $body .= '<div style="margin-top: 16px; margin-bottom: 8px; font-weight: bold; color: #f59e0b;">';
            $body .= 'Business Name Update Failures:';
            $body .= '</div>';
            $body .= '<div style="margin-left: 12px; font-size: 0.875rem;">';
            foreach ($results['name_update_errors'] as $error) {
                $body .= '• ' . htmlspecialchars($error) . '<br>';
            }
            $body .= '</div>';
        }

        $body .= '</div>';

        if ($results['success'] === $results['total'] && $results['total'] > 0 && $results['name_update_failed'] === 0) {
            // Perfect success - all imported and all names updated
            Notification::make()
                ->title($title)
                ->body(new \Illuminate\Support\HtmlString($body))
                ->success()
                ->persistent()
                ->send();
        } elseif ($results['success'] > 0) {
            // Partial success or success with name update failures
            Notification::make()
                ->title($title)
                ->body(new \Illuminate\Support\HtmlString($body))
                ->warning()
                ->persistent()
                ->send();
        } else {
            // Complete failure
            Notification::make()
                ->title('Import Failed')
                ->body(new \Illuminate\Support\HtmlString($body))
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
