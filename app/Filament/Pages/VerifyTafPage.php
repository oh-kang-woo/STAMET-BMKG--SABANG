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

    public function getRealtimeDataProperty(): array
    {
        $date = Carbon::parse($this->selectedDate);
        $cacheKey = "taf_realtime_v2_{$this->icao}_{$date->format('Y-m-d')}";

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

            // Variabel Statistik
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
                    if ($targetTime > $now && $date->isToday()) break;

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
                    $slotPassed = false;

                    if ($activeTaf && $activeObs) {
                        $groups = $verifier->matchingGroups($activeTaf, $targetTime);
                        $expectedGroup = end($groups);
                        $groupKind = $expectedGroup->kind;
                        $scores = $verifier->calculateScore($expectedGroup->condition, $activeObs->condition, $activeObs->raw);

                        $totalEvaluated++;
                        $sumScore = array_sum($scores);
                        if ($sumScore === 7) {
                            $passedSlots++;
                            $slotPassed = true;
                        } else {
                            $failedSlots++;
                        }

                        foreach ($scores as $key => $val) {
                            $paramStats[$key]['total']++;
                            if ($val === 1) $paramStats[$key]['pass']++;
                        }
                    }

                    $rows[] = [
                        'time' => $targetTime->format('H:i') . ' UTC',
                        'taf_raw' => $activeTaf?->raw ?? 'Data TAF Belum Tersedia',
                        'metar_raw' => $activeObs?->raw ?? 'Data METAR Belum Tersedia',
                        'group' => $groupKind,
                        'scores' => $scores,
                        'passed' => $slotPassed
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
                ]
            ];
        });
    }

    public function refreshData(): void
    {
        $cacheKey = "taf_realtime_v2_{$this->icao}_" . Carbon::parse($this->selectedDate)->format('Y-m-d');
        Cache::forget($cacheKey);
    }
}