<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class BmkgAviationApiService
{
    protected string $tafUrl = 'https://web-aviation.bmkg.go.id/web/taf.php';
    protected string $metarUrl = 'https://web-aviation.bmkg.go.id/web/metar_speci.php';

    public function collectData(string $icao, Carbon $start, Carbon $end): array
    {
        $anchor = $start->copy()->addDays(15);
        $payload = [
            'icao' => $icao,
            'start' => $start->format('d/m/Y H:i'),
            'end' => $end->format('d/m/Y H:i'),
        ];

        // 1. Scraping data TAF mentah dari portal BMKG
        $tafResponse = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36'
            ])
            ->asForm()
            ->post($this->tafUrl, $payload);

        preg_match_all('/(FTID40\s+'.$icao.'.*?=)/s', $tafResponse->body(), $tafMatches);
        $tafs = [];
        foreach ($tafMatches[0] ?? [] as $raw) {
            $parsed = TafParserService::parseTaf($raw, $anchor->toDateTime());
            if ($parsed) {
                $tafs[] = $parsed;
            }
        }

        // 2. Scraping data METAR / SPECI mentah dari portal BMKG
        $metarResponse = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36'
            ])
            ->asForm()
            ->post($this->metarUrl, $payload);

        preg_match_all('/((?:METAR|SPECI)(?:\s+COR)?\s+'.$icao.'.*?=)/s', $metarResponse->body(), $metarMatches);
        $observations = [];
        foreach ($metarMatches[0] ?? [] as $raw) {
            $parsed = TafParserService::parseObservation($raw, $anchor->toDateTime());
            if ($parsed) {
                $observations[] = $parsed;
            }
        }

        return [
            'tafs' => $tafs,
            'observations' => $observations,
        ];
    }
}