<?php

namespace App\Filament\Pages;

use App\Services\BmkgAviationApiService;
use App\Services\TafReportExcelService;
use App\Services\TafVerifierService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
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

    // === TAMBAHAN UNTUK FITUR QC ===
    public string $rawTaf = '';
    public array $qcResult = [];

    public function checkQualityControl(): void
    {
        $rawTaf = trim(preg_replace('/\s+/', ' ', str_replace('=', ' = ', strtoupper($this->rawTaf))));
        $clean = trim(str_replace(' =', '=', $rawTaf));
        
        if (empty($clean)) {
            $this->qcResult = [];
            return;
        }

        $results = [];
        $errors = 0;

        $addResult = function($element, $rule, $remark, $isValid) use (&$results, &$errors) {
            $results[] = [
                'element' => $element,
                'rule' => $rule,
                'remark' => $remark,
                'is_valid' => $isValid
            ];
            if (!$isValid) {
                $errors++;
            }
        };

        // 1. Header & ICAO
        $requiredHeaderOk = (bool) preg_match('/^FTID40\s+WITN\b/', $clean);
        $addResult(
            "FTID40 WITN", 
            "QC Header TAF", 
            $requiredHeaderOk ? "Header awal sesuai ketentuan: FTID40 WITN." : "TAF TIDAK VALID: header awal bukan FTID40 WITN.", 
            $requiredHeaderOk
        );

        preg_match('/\b(TAF(?:\s+(?:AMD|COR))?)\s+(WITN)\s+(\d{6})Z\s+(\d{4})\/(\d{4})\b/', $clean, $headerMatch);
        
        if ($headerMatch) {
            $tafType = $headerMatch[1];
            $icao = $headerMatch[2];
            $issue = $headerMatch[3] . 'Z';
            $valid = $headerMatch[4] . '/' . $headerMatch[5];

            $addResult("$tafType WITN", "Identifikasi TAF", "Header TAF dan jenis sandi dikenali.", $requiredHeaderOk);
            $addResult($tafType, "Jenis prakiraan", "Jenis prakiraan valid.", true);
            $addResult($icao, "Indikator lokasi", "Indikator lokasi (WITN) valid.", true);
            
            // Note: PHP Carbon logic for exact time validation based on the Python script would require full parsing.
            // Simplified validation for issue and validity format based on regex for this scope.
            $addResult($issue, "Waktu Penerbitan", "Format Waktu Penerbitan Valid.", true);
            $addResult($valid, "Periode Validitas", "Format Validitas Valid.", true);
        } else {
            $addResult("Header", "Identifikasi TAF", "Header tidak dikenali sesuai format TAF WITN.", false);
        }

        // 2. Pembagian Grup (GENERAL, BECMG, TEMPO, dll)
        $bodyForOrder = $headerMatch ? trim(substr($clean, strlen($headerMatch[0]) + strpos($clean, $headerMatch[0]))) : $clean;
        preg_match_all('/\b(BECMG|TEMPO|INTER|PROB30|PROB40|FM\d{6})\b/', $bodyForOrder, $markers, PREG_OFFSET_CAPTURE);
        
        $orderChunks = [];
        $firstEnd = $markers[0] ? $markers[0][0][1] : strlen($bodyForOrder);
        $orderChunks[] = ["GENERAL", substr($bodyForOrder, 0, $firstEnd)];
        
        $oi = 0;
        $markerCount = count($markers[0] ?? []);
        while ($oi < $markerCount) {
            $label = $markers[0][$oi][0];
            $startPos = $markers[0][$oi][1];
            $nextStartPos = ($oi + 1 < $markerCount) ? $markers[0][$oi + 1][1] : strlen($bodyForOrder);
            
            if (in_array($label, ["PROB30", "PROB40"]) && ($oi + 1 < $markerCount) && $markers[0][$oi + 1][0] == "TEMPO") {
                $label .= " TEMPO";
                $nextStartPos = ($oi + 2 < $markerCount) ? $markers[0][$oi + 2][1] : strlen($bodyForOrder);
                $oi += 2;
            } else {
                $oi += 1;
            }
            $orderChunks[] = [$label, substr($bodyForOrder, $startPos, $nextStartPos - $startPos)];
        }

        // 3. Pengecekan Urutan Parameter per Grup
        $ranks = ["wind" => 0, "visibility" => 1, "weather" => 2, "cloud" => 3, "wind_shear" => 4];
        
        foreach ($orderChunks as $chunk) {
            $groupLabel = $chunk[0];
            $groupText = $chunk[1];
            
            $elementText = preg_replace('/^\s*(?:(?:PROB30|PROB40)\s+TEMPO|BECMG|TEMPO|INTER|PROB30|PROB40|FM\d{6})\b/', '', $groupText);
            $elementText = preg_replace('/\b\d{4}\/\d{4}\b/', '', $elementText);
            
            $found = [];
            
            if (preg_match_all('/\b(?:VRB|\d{3})\d{2,3}(?:G\d{2,3})?KT\b/', $elementText, $m, PREG_OFFSET_CAPTURE)) {
                foreach($m[0] as $match) $found[] = ["wind", $match[1], $match[0]];
            }
            if (preg_match_all('/\b(?:CAVOK|\d{4}|VV(?:\d{3}|[\/]{3}))\b/', $elementText, $m, PREG_OFFSET_CAPTURE)) {
                foreach($m[0] as $match) $found[] = ["visibility", $match[1], $match[0]];
            }
            if (preg_match_all('/\b(?:NSW|[+-]?(?:(?:MI|PR|BC|DR|BL|SH|TS|FZ))?(?:DZ|RA|SN|SG|IC|PL|GR|GS|UP|FG|BR|SA|DU|HZ|FU|VA|SQ|PO|FC|SS|DS))\b/', $elementText, $m, PREG_OFFSET_CAPTURE)) {
                foreach($m[0] as $match) $found[] = ["weather", $match[1], $match[0]];
            }
            if (preg_match_all('/\b(?:FEW|SCT|BKN|OVC)\d{3}(?:CB|TCU)?\b|\b(?:NSC|NCD|SKC)\b/', $elementText, $m, PREG_OFFSET_CAPTURE)) {
                foreach($m[0] as $match) $found[] = ["cloud", $match[1], $match[0]];
            }
            if (preg_match_all('/\bWS\d{3}\/\d{3}\d{2,3}KT\b/', $elementText, $m, PREG_OFFSET_CAPTURE)) {
                foreach($m[0] as $match) $found[] = ["wind_shear", $match[1], $match[0]];
            }

            usort($found, function($a, $b) { return $a[1] <=> $b[1]; });
            
            $rankSequence = array_map(function($item) use ($ranks) { return $ranks[$item[0]]; }, $found);
            $sortedRankSequence = $rankSequence;
            sort($sortedRankSequence);
            
            $badOrder = $rankSequence !== $sortedRankSequence;
            $tokens = implode(" ", array_column($found, 2));

            if ($badOrder) {
                $addResult($groupLabel, "Urutan parameter", "Urutan tidak sesuai (Angin → Vis → Cuaca → Awan). Ditemukan: $tokens.", false);
            } elseif (!empty($found)) {
                $addResult($groupLabel, "Urutan parameter", "Urutan parameter sesuai. Ditemukan: $tokens.", true);
            }

            // Cek urutan tinggi awan
            if (preg_match_all('/\b(?:FEW|SCT|BKN|OVC)(\d{3})(?:CB|TCU)?\b/', $elementText, $cloudMatches)) {
                $heights = array_map('intval', $cloudMatches[1]);
                $sortedHeights = $heights;
                sort($sortedHeights);
                if (count($heights) > 1 && $heights !== $sortedHeights) {
                    $addResult($groupLabel, "Urutan lapisan awan", "Lapisan awan tidak berurutan dari dasar terendah ke tertinggi.", false);
                } else {
                    $addResult($groupLabel, "Urutan lapisan awan", "Lapisan awan berurutan dari dasar terendah ke tertinggi.", true);
                }
            }
        }

        // 4. Aturan NSW
        $getRainIntensity = function($text) {
            if (preg_match_all('/(?<![A-Z0-9])([+-]?(?:SH)?RA)(?![A-Z0-9])/', $text, $matches)) {
                return $matches[1][0];
            }
            return null;
        };

        $previousWeather = $getRainIntensity($orderChunks[0][1]);
        $previousHadSignificantRain = in_array($previousWeather, ["RA", "+RA", "SHRA", "+SHRA"]);

        for ($i = 1; $i < count($orderChunks); $i++) {
            $groupLabel = $orderChunks[$i][0];
            $groupText = $orderChunks[$i][1];

            if (!preg_match('/^(?:TEMPO|BECMG|PROB30 TEMPO|PROB40 TEMPO)/', $groupLabel)) {
                $currentWeather = $getRainIntensity($groupText);
                if ($currentWeather) {
                    $previousHadSignificantRain = in_array($currentWeather, ["RA", "+RA", "SHRA", "+SHRA"]);
                }
                continue;
            }

            $currentWeather = $getRainIntensity($groupText);
            $hasNsw = (bool) preg_match('/\bNSW\b/', $groupText);

            if (in_array($currentWeather, ["-RA", "-SHRA"]) && $hasNsw) {
                $addResult($groupLabel, "Aturan NSW", "NSW tidak boleh digunakan jika hujan masih diprakirakan (-RA/-SHRA).", false);
            } elseif ($previousHadSignificantRain && in_array($currentWeather, ["-RA", "-SHRA"])) {
                $addResult($groupLabel, "Aturan NSW", "Hujan diprakirakan $currentWeather; NSW tidak boleh menggantikannya.", false);
            } elseif ($previousHadSignificantRain && !$currentWeather && !$hasNsw) {
                $addResult($groupLabel, "Aturan NSW", "Sebelumnya diprakirakan RA/+RA. Jika hujan berakhir, cantumkan NSW.", false);
            } elseif ($hasNsw) {
                $addResult($groupLabel, "Aturan NSW", "NSW digunakan dengan benar untuk menandai berakhirnya cuaca signifikan.", true);
            }

            $previousWeather = $currentWeather ?: ($hasNsw ? "NONE" : $previousWeather);
            if ($currentWeather) {
                $previousHadSignificantRain = in_array($currentWeather, ["RA", "+RA", "SHRA", "+SHRA"]);
            } elseif ($hasNsw) {
                $previousHadSignificantRain = false;
            }
        }

        // 5. Mandatory Elements in GENERAL Group
        $generalText = $orderChunks[0][1];
        
        $windOk = (bool) preg_match('/\b(?:VRB|\d{3})\d{2,3}(?:G\d{2,3})?KT\b/', $generalText, $windMatch);
        $addResult($windMatch[0] ?? "Angin", "Kelompok Angin Permukaan", $windOk ? "Format Angin Valid." : "Kelompok angin tidak ditemukan.", $windOk);

        $visOk = (bool) preg_match('/\b(?:\d{4}|CAVOK)\b/', $generalText, $visMatch);
        $addResult($visMatch[0] ?? "Visibility", "Kelompok Visibility", $visOk ? "Format Visibility Valid." : "Visibility/CAVOK tidak ditemukan.", $visOk);

        $cloudOk = (bool) preg_match('/\b(?:CAVOK|FEW|SCT|BKN|OVC)\d{3}(?:CB|TCU)?\b|\b(?:NSC|NCD|SKC)\b/', $generalText, $cloudMatch);
        $addResult($cloudMatch[0] ?? "Cloud", "Kelompok Awan", $cloudOk ? "Kelompok awan/clear-sky dikenali." : "Kelompok awan/clear-sky tidak ditemukan.", $cloudOk);

        // 6. PROB Check
        if (preg_match_all('/\bPROB(?!30\b|40\b)\d{2}\b/', $clean, $badProbs)) {
            $addResult(implode(" ", array_unique($badProbs[0])), "Sandi PROB", "Nilai probabilitas selain PROB30/PROB40 ditemukan.", false);
        } elseif (preg_match_all('/\bPROB(?:30|40)\b/', $clean, $goodProbs)) {
            $addResult(implode(" / ", array_unique($goodProbs[0])), "Sandi PROB", "Nilai probabilitas valid.", true);
        }

        // 7. Closing Marker
        if (str_ends_with(trim($clean), '=')) {
            $addResult("=", "Tanda Penutup", "Terdapat tanda (=) di akhir berita.", true);
        } else {
            $addResult("=", "Tanda Penutup", "Tanda (=) tidak ditemukan di akhir berita.", false);
        }

        $this->qcResult = [
            'results' => $results,
            'errors' => $errors,
            'is_valid' => $errors === 0
        ];
    }

    public function clearQualityControl(): void
    {
        $this->rawTaf = '';
        $this->qcResult = [];
    }
    // === AKHIR TAMBAHAN QC ===

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

            // PERBAIKAN: Mundurkan $start 1 hari untuk mengambil TAF jam 23:00 UTC hari sebelumnya
            $start = $date->copy()->subDay()->startOfDay(); 
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
                Select::make('month')
                    ->label('Bulan Laporan')
                    ->options([
                        '01' => 'Januari',
                        '02' => 'Februari',
                        '03' => 'Maret',
                        '04' => 'April',
                        '05' => 'Mei',
                        '06' => 'Juni',
                        '07' => 'Juli',
                        '08' => 'Agustus',
                        '09' => 'September',
                        '10' => 'Oktober',
                        '11' => 'November',
                        '12' => 'Desember',
                    ])
                    ->default(now()->format('m'))
                    ->required(),

                Select::make('year')
                    ->label('Tahun Laporan')
                    ->options(function () {
                        $currentYear = (int) now()->format('Y');
                        $years = [];
                        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                            $years[$y] = (string) $y;
                        }
                        return $years;
                    })
                    ->default(now()->format('Y'))
                    ->required(),
            ])
            ->action(function (array $data, TafReportExcelService $excelService) {
                set_time_limit(300);

                $date = Carbon::createFromDate((int)$data['year'], (int)$data['month'], 1)->startOfDay();

                try {
                    $api = app(BmkgAviationApiService::class);
                    
                    // PERBAIKAN: Mundurkan $start beberapa hari ke bulan sebelumnya agar TAF akhir bulan terbawa
                    $start = $date->copy()->subDays(2)->startOfMonth(); 
                    $end = $date->copy()->endOfMonth();

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