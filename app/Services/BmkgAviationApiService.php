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
        // Carbon sudah merupakan turunan dari DateTime, tidak perlu ->toDateTime()
        $anchor = $start->copy()->addDays(15);
        
        $payload = [
            'icao' => $icao,
            'start' => $start->format('d/m/Y H:i'),
            'end' => $end->format('d/m/Y H:i'),
        ];

        // 1. Scraping TAF
        $tafResponse = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'Referer' => 'https://web-aviation.bmkg.go.id/'
            ])
            ->asForm()
            ->post($this->tafUrl, $payload);

        $cleanTafBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $tafResponse->body()));
        
        preg_match_all('/(?:FTID40\s+'.$icao.'.*?=|(?:TAF(?:\s+(?:AMD|COR))?\s+'.$icao.'.*?=))/s', $cleanTafBody, $tafMatches);
        
        $tafs = [];
        foreach ($tafMatches[0] ?? [] as $raw) {
            $parsed = TafParserService::parseTaf($raw, $anchor);
            if ($parsed) {
                $tafs[] = $parsed;
            }
        }

        // 2. Scraping METAR / SPECI
        $metarResponse = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'Referer' => 'https://web-aviation.bmkg.go.id/'
            ])
            ->asForm()
            ->post($this->metarUrl, $payload);

        $cleanMetarBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $metarResponse->body()));
        
        preg_match_all('/((?:METAR|SPECI)(?:\s+COR)?\s+'.$icao.'.*?=)/s', $cleanMetarBody, $metarMatches);
        
        $observations = [];
        foreach ($metarMatches[0] ?? [] as $raw) {
            $parsed = TafParserService::parseObservation($raw, $anchor);
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