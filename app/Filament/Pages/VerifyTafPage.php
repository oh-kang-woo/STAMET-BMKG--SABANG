<?php

namespace App\Filament\Pages;

use App\Services\BmkgAviationApiService;
use App\Services\TafReportExcelService;
use App\Services\TafVerifierService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class VerifyTafPage extends Page implements HasActions
{
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static string $view = 'filament.pages.verify-taf-page';
    protected static ?string $title = 'Verifikasi TAF Realtime';
    protected static ?string $slug = 'verify-taf-page';

    public string $selectedDate;
    public string $icao = 'WITN';

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public function getRealtimeDataProperty(): array
    {
        $date = Carbon::parse($this->selectedDate);
        $cacheKey = "taf_realtime_v3_{$this->icao}_{$date->format('Y-m-d')}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($date) {
            $api = app(BmkgAviationApiService::class);
            $verifier = app(TafVerifierService::class);

            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $rawData = $api->collectData($this->icao, $start, $end);
            $tafs = $rawData['tafs'];
            $observations = $rawData['observations'];

            $rows = [];
            $now = now();
            $maxHour = $date->isToday() ? $now->hour : 23;

            $totalEvaluated = 0;
            $passedSlots = 0;
            $failedSlots = 0;
            $paramStats = [
                'wind_dir' => ['pass' => 0, 'total' => 0, 'label' => 'Arah Angin'],
                'wind_speed' => ['pass' => 0, 'total' => 0, 'label' => 'Kecepatan Angin'],
                'gust' => ['pass' => 0, 'total' => 0, 'label' => 'Gust'],
                'visibility' => ['pass' => 0, 'total' => 0, 'label' => 'Visibility'],
                'precip' => ['pass' => 0, 'total' => 0, 'label' => 'Cuaca'],
                'cloud' => ['pass' => 0, 'total' => 0, 'label' => 'Awan'],
                'ceiling' => ['pass' => 0, 'total' => 0, 'label' => 'Ceiling'],
            ];

            for ($hour = 0; $hour <= $maxHour; $hour++) {
                foreach ([0, 30] as $minute) {
                    $targetTime = $date->copy()->setTime($hour, $minute);
                    if ($targetTime > $now && $date->isToday()) {
                        break;
                    }

                    $activeTaf = null;
                    foreach ($tafs as $t) {
                        $vStart = Carbon::parse($t->validStart ?? $t->valid_start);
                        $vEnd = Carbon::parse($t->validEnd ?? $t->valid_end);
                        if ($vStart <= $targetTime && $targetTime <= $vEnd) {
                            $activeTaf = $t;
                            break;
                        }
                    }

                    $activeObs = null;
                    foreach ($observations as $obs) {
                        $obsAt = Carbon::parse($obs->at);
                        if (abs($obsAt->getTimestamp() - $targetTime->getTimestamp()) <= 900) {
                            $activeObs = $obs;
                            break;
                        }
                    }

                    $scores = null;
                    $groupKind = '-';
                    $slotPassed = false;

                    if ($activeTaf && $activeObs) {
                        $match = $verifier->scoreForTime($activeTaf, $activeObs);
                        $expectedGroup = $match['group'];

                        $groupKind = is_object($expectedGroup) ? $expectedGroup->kind : ($expectedGroup['kind'] ?? '-');
                        $scores = $match['scores'];

                        $totalEvaluated++;
                        $sumScore = array_sum($scores);
                        if ($sumScore === 7) {
                            $passedSlots++;
                            $slotPassed = true;
                        } else {
                            $failedSlots++;
                        }

                        foreach ($scores as $key => $val) {
                            if (isset($paramStats[$key])) {
                                $paramStats[$key]['total']++;
                                if ($val === 1) {
                                    $paramStats[$key]['pass']++;
                                }
                            }
                        }
                    }

                    $rows[] = [
                        'time' => $targetTime->format('H:i') . ' UTC',
                        'taf_raw' => $activeTaf?->raw ?? 'Data TAF Belum Tersedia',
                        'metar_raw' => $activeObs?->raw ?? 'Data METAR Belum Tersedia',
                        'group' => $groupKind,
                        'scores' => $scores,
                        'passed' => $slotPassed,
                    ];
                }
            }

            $overallAccuracy = $totalEvaluated > 0 ? round(($passedSlots / $totalEvaluated) * 100, 1) : 0;

            return [
                'rows' => array_reverse($rows),
                'summary' => [
                    'total' => $totalEvaluated,
                    'passed' => $passedSlots,
                    'failed' => $failedSlots,
                    'accuracy' => $overallAccuracy,
                    'paramStats' => $paramStats,
                ],
            ];
        });
    }

    public function refreshData(): void
    {
        $cacheKey = "taf_realtime_v3_{$this->icao}_" . Carbon::parse($this->selectedDate)->format('Y-m-d');
        Cache::forget($cacheKey);
    }

    public function exportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Download Excel')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->size('sm')
            ->modalHeading('Unduh Laporan Verifikasi TAF')
            ->modalDescription('Pilih bulan dan tahun laporan yang ingin Anda unduh ke dalam format Excel.')
            ->modalSubmitActionLabel('Unduh Laporan')
            ->form([
                DatePicker::make('month')
                    ->label('Bulan Laporan')
                    ->format('Y-m')
                    ->displayFormat('F Y')
                    ->native(false)
                    ->required()
                    ->default(now()->format('Y-m')),
            ])
            ->action(function (array $data, TafReportExcelService $excelService) {
                set_time_limit(300);

                // Parsing aman tanggal awal bulan
                $date = Carbon::parse($data['month'])->startOfMonth();

                try {
                    $api = app(BmkgAviationApiService::class);
                    // Rentang penarikan data diperluas (+/- 1 hari) untuk antisipasi TAF/METAR batas bulan
                    $start = $date->copy()->subDays(1)->startOfDay();
                    $end = $date->copy()->endOfMonth()->addDays(1)->endOfDay();

                    $rawData = $api->collectData($this->icao, $start, $end);
                    $tafs = $rawData['tafs'];
                    $observations = $rawData['observations'];

                    $fileName = $excelService->generate($date, $tafs, $observations);
                    $filePath = storage_path('app/public/' . $fileName);

                    if (!file_exists($filePath)) {
                        throw new \Exception("File hasil export Excel tidak ditemukan.");
                    }

                    return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Gagal Mengunduh Laporan')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}