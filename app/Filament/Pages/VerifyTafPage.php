<?php

namespace App\Filament\Pages;

use App\Services\BmkgAviationApiService;
use App\Services\TafReportExcelService;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\Storage;

class VerifyTafPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Verifikasi TAFOR';
    protected static ?string $title = 'Verifikasi TAFOR';
    protected static string $view = 'filament.pages.verify-taf-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('start')
                    ->label('Mulai Periode (UTC)')
                    ->required(),
                DateTimePicker::make('end')
                    ->label('Akhir Periode (UTC)')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(BmkgAviationApiService $apiService, TafReportExcelService $excelService)
    {
        $state = $this->form->getState();
        $start = Carbon::parse($state['start'], 'UTC');
        $end = Carbon::parse($state['end'], 'UTC');

        $rawData = $apiService->collectData('WITN', $start, $end);
        
        $fileName = $excelService->generate($start, $rawData['tafs'], $rawData['observations']);

        Notification::make()
            ->title('Proses Verifikasi Selesai')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Unduh File Excel')
                    ->button()
                    ->url(Storage::url($fileName), shouldOpenInNewTab: true),
            ])
            ->send();
    }
}