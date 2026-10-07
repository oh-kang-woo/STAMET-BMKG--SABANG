<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BmkgAviationApiService
{
    protected string $tafUrl = 'https://web-aviation.bmkg.go.id/web/taf.php';
    protected string $metarUrl = 'https://web-aviation.bmkg.go.id/web/metar_speci.php';

    public function collectData(string $icao, Carbon $start, Carbon $end): array
    {
        $anchor = $start->copy()->addDays(15);

        // 1. Scraping TAF
        $rawTafHtml = $this->fetchFromBmkg($this->tafUrl, $icao, $start, $end);
        $tafMatches = $this->extractTafMessages($rawTafHtml, $icao);

        $tafs = [];
        foreach ($tafMatches as $raw) {
            $parsed = TafParserService::parseTaf($raw, $anchor);
            if ($parsed) {
                $tafs[] = $parsed;
            }
        }

        // 2. Scraping METAR / SPECI
        $rawMetarHtml = $this->fetchFromBmkg($this->metarUrl, $icao, $start, $end);
        $metarMatches = $this->extractMetarMessages($rawMetarHtml, $icao);

        $observations = [];
        foreach ($metarMatches as $raw) {
            $parsed = TafParserService::parseObservation($raw, $anchor);
            if ($parsed) {
                $observations[] = $parsed;
            }
        }

        Log::info("BMKG Scraping [{$icao}]: Found " . count($tafs) . " TAFs and " . count($observations) . " METARs.");

        return [
            'tafs' => $tafs,
            'observations' => $observations,
        ];
    }

    protected function fetchFromBmkg(string $url, string $icao, Carbon $start, Carbon $end): string
    {
        // Step 1: GET halaman awal untuk mengambil CSRF Token (_token) & Cookie Sesi
        $get = Http::withoutVerifying()
            ->timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->get($url);

        // Extract CSRF token dari input hidden
        preg_match('/name="_token"\s+value="([^"]+)"/i', $get->body(), $matches);
        $token = $matches[1] ?? '';

        // Re-format cookies menjadi key => value biasa agar tidak memicu warning di Guzzle
        $cookies = [];
        foreach ($get->cookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Step 2: POST dengan payload standar form BMKG
        $post = Http::withoutVerifying()
            ->timeout(60)
            ->withCookies($cookies, $host)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
                'Referer' => $url,
            ])
            ->asForm()
            ->post($url, [
                '_token'  => $token,
                'stasiun' => $icao,
                'from'    => $start->format('Y-m-d\TH:i'),
                'to'      => $end->format('Y-m-d\TH:i'),
            ]);

        return $post->body();
    }

    protected function extractTafMessages(string $html, string $icao): array
    {
        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />', '&nbsp;'], ["\n", "\n", "\n", " "], $html));
        preg_match_all('/(?:FTID40\s+'.$icao.'.*?=|(?:TAF(?:\s+(?:AMD|COR))?\s+'.$icao.'.*?=))/si', $plain, $matches);
        return array_map('trim', $matches[0] ?? []);
    }

    protected function extractMetarMessages(string $html, string $icao): array
    {
        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />', '&nbsp;'], ["\n", "\n", "\n", " "], $html));
        preg_match_all('/((?:METAR|SPECI)(?:\s+COR)?\s+'.$icao.'.*?=)/si', $plain, $matches);
        return array_map('trim', $matches[0] ?? []);
    }
}