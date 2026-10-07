<?php

namespace App\Filament\Pages;

use App\Services\BmkgAviationApiService;
use App\Services\TafVerifierService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class VerifyTafPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static string $view = 'filament.pages.verify-taf-page';
    protected static ?string $title = 'Verifikasi TAF Realtime';

    public string $selectedDate;
    public string $icao = 'WITN';

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
    }

    public function getRealtimeVerificationProperty(): array
    {
        $date = Carbon::parse($this->selectedDate);
        $cacheKey = "taf_realtime_{$this->icao}_{$date->format('Y-m-d')}";

        // Cache selama 10 menit agar tidak membebankan server BMKG
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($date) {
            $api = app(BmkgAviationApiService::class);
            $verifier = app(TafVerifierService::class);

            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $rawData = $api->collectData($this->icao, $start, $end);
            $tafs = $rawData['tafs'];
            $observations = $rawData['observations'];

            $rows = [];
            $now = now();

            // Iterasi per jam dari jam 00:00 sampai jam saat ini (atau 23:00)
            $maxHour = $date->isToday() ? $now->hour : 23;

            for ($hour = 0; $hour <= $maxHour; $hour++) {
                foreach ([0, 30] as $minute) {
                    $targetTime = $date->copy()->setTime($hour, $minute);
                    
                    if ($targetTime > $now && $date->isToday()) {
                        break;
                    }

                    // Cari TAF dan METAR aktif pada slot waktu ini
                    $activeTaf = null;
                    foreach ($tafs as $t) {
                        if ($t->validStart <= $targetTime && $targetTime <= $t->validEnd) {
                            $activeTaf = $t;
                            break;
                        }
                    }

                    $activeObs = null;
                    foreach ($observations as $obs) {
                        if (abs($obs->at->getTimestamp() - $targetTime->getTimestamp()) <= 900) {
                            $activeObs = $obs;
                            break;
                        }
                    }

                    $scores = null;
                    $groupKind = '-';

                    if ($activeTaf && $activeObs) {
                        $groups = $verifier->matchingGroups($activeTaf, $targetTime);
                        $expectedGroup = end($groups);
                        $groupKind = $expectedGroup->kind;
                        $scores = $verifier->calculateScore($expectedGroup->condition, $activeObs->condition, $activeObs->raw);
                    }

                    $rows[] = [
                        'time' => $targetTime->format('H:i') . ' UTC',
                        'taf_raw' => $activeTaf?->raw ?? 'Data TAF Tidak Ada',
                        'metar_raw' => $activeObs?->raw ?? 'Data METAR Belum Tersedia',
                        'group' => $groupKind,
                        'scores' => $scores,
                    ];
                }
            }

            return array_reverse($rows); // Tampilkan waktu terbaru di paling atas
        });
    }

    public function refreshData(): void
    {
        $cacheKey = "taf_realtime_{$this->icao}_" . Carbon::parse($this->selectedDate)->format('Y-m-d');
        Cache::forget($cacheKey);
    }
}