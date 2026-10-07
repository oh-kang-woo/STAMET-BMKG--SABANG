<?php

namespace App\Services;

use App\DTOs\Taf\Condition;
use App\DTOs\Taf\Group;
use App\DTOs\Taf\ObservationData;
use App\DTOs\Taf\TafData;
use DateTime;
use DateTimeZone;
use DateInterval;

class TafParserService
{
    public static function resolveDate(int $day, int $hour, int $minute, DateTime $anchor): DateTime
    {
        $candidates = [];
        $utc = new DateTimeZone('UTC');

        for ($offset = -2; $offset <= 2; $offset++) {
            $totalMonth = ($anchor->format('Y') * 12) + ($anchor->format('n') - 1) + $offset;
            $y = intdiv($totalMonth, 12);
            $m = ($totalMonth % 12) + 1;

            if (checkdate($m, $day, $y)) {
                $dt = new DateTime('now', $utc);
                $dt->setDate($y, $m, $day);
                $dt->setTime($hour, $minute, 0);
                $candidates[] = $dt;
            }
        }

        usort($candidates, fn($a, $b) => abs($a->getTimestamp() - $anchor->getTimestamp()) <=> abs($b->getTimestamp() - $anchor->getTimestamp()));

        return $candidates[0] ?? new DateTime('now', $utc);
    }

    public static function parseCondition(string $text): Condition
    {
        $c = new Condition();

        // Wind Direction & Speed
        if (preg_match('/\b(VRB|\d{3})(\d{2,3})(G\d{2,3})?KT\b/i', $text, $m)) {
            $c->windDir = ($m[1] === 'VRB') ? 999 : (int)$m[1];
            $c->windSpeed = (int)$m[2];
            $c->gust = !empty($m[3]);
        }

        // Visibility & Cloud
        if (preg_match('/\bCAVOK\b/i', $text)) {
            $c->visibility = 9999;
            $c->cloud = 'SKC';
            $c->ceilingFt = 9999;
        } else {
            if (preg_match('/(?:^|\s)(\d{4})(?:\s|$)/', $text, $m)) {
                $c->visibility = (int)$m[1];
            }

            if (preg_match_all('/\b(FEW|SCT|BKN|OVC)(\d{3})(?:CB|TCU)?\b/i', $text, $m, PREG_SET_ORDER)) {
                usort($m, fn($a, $b) => (int)$a[2] <=> (int)$b[2]);
                $c->cloud = strtoupper($m[0][1]);
                $c->ceilingFt = (int)$m[0][2] * 100;
            } elseif (preg_match('/\b(NSC|NCD|SKC)\b/i', $text)) {
                $c->cloud = 'SKC';
                $c->ceilingFt = 9999;
            }
        }

        // Precipitation (Mendukung TSRA, SHRA, RA, DZ, dll)
        if (preg_match_all('/(?<![A-Z0-9])([+-]?(?:TS|SH)?(?:RA|DZ|SN|SG|PL|GR|GS))(?![A-Z0-9])/i', $text, $m)) {
            $rainTokens = array_filter($m[1], fn($w) => str_contains(strtoupper($w), 'RA'));
            $c->precipCode = !empty($rainTokens) ? strtoupper(array_values($rainTokens)[0]) : 'NONE';
        } else {
            $c->precipCode = 'NONE';
        }

        return $c;
    }

    public static function parseTaf(string $raw, ?DateTime $anchor = null): ?TafData
    {
        $raw = trim(preg_replace('/\s+/', ' ', str_replace('=', '', $raw)));
        $hasHeader = (bool)preg_match('/^FTID40\s+WITN\b/i', $raw);

        if (!preg_match('/\bTAF(?:\s+(?:AMD|COR))?\s+(WITN)\s+(\d{2})(\d{2})(\d{2})Z\s+(\d{2})(\d{2})\/(\d{2})(\d{2})/i', $raw, $m)) {
            return null;
        }

        $anchor = $anchor ?? new DateTime('now', new DateTimeZone('UTC'));
        $start = self::resolveDate((int)$m[5], (int)$m[6], 0, $anchor);
        $issued = self::resolveDate((int)$m[2], (int)$m[3], (int)$m[4], $start);
        $end = (clone $start)->add(new DateInterval('P1D'))->setTime((int)$m[8], 0, 0);

        $body = trim(substr($raw, strpos($raw, $m[0]) + strlen($m[0])));
        preg_match_all('/\b(BECMG|TEMPO|INTER|PROB30|PROB40|FM\d{6})\b/i', $body, $markers, PREG_OFFSET_CAPTURE);

        $chunks = [];
        if (!empty($markers[0])) {
            $chunks[] = ['GENERAL', trim(substr($body, 0, $markers[0][0][1]))];
            for ($i = 0; $i < count($markers[0]); $i++) {
                $label = strtoupper($markers[0][$i][0]);
                $startPos = $markers[0][$i][1];
                $nextPos = ($i + 1 < count($markers[0])) ? $markers[0][$i + 1][1] : strlen($body);

                if (in_array($label, ['PROB30', 'PROB40']) && $i + 1 < count($markers[0]) && strtoupper($markers[0][$i + 1][0]) === 'TEMPO') {
                    $label .= ' TEMPO';
                    $nextPos = ($i + 2 < count($markers[0])) ? $markers[0][$i + 2][1] : strlen($body);
                    $i++;
                }

                $chunks[] = [$label, trim(substr($body, $startPos, $nextPos - $startPos))];
            }
        } else {
            $chunks[] = ['GENERAL', $body];
        }

        $base = self::parseCondition($chunks[0][1]);
        if ($base->gust === null) $base->gust = false;

        $groups = [new Group('GENERAL', $start, $end, $base)];
        $current = $base;

        for ($k = 1; $k < count($chunks); $k++) {
            [$kind, $piece] = $chunks[$k];
            if (preg_match('/FM(\d{2})(\d{2})(\d{2})/i', $piece, $fm)) {
                $gs = self::resolveDate((int)$fm[1], (int)$fm[2], (int)$fm[3], $start);
                $ge = $end;
            } elseif (preg_match('/(\d{2})(\d{2})\/(\d{2})(\d{2})/', $piece, $tm)) {
                $gs = self::resolveDate((int)$tm[1], (int)$tm[2], 0, $start);
                $ge = self::resolveDate((int)$tm[3], (int)$tm[4], 0, (clone $gs)->add(new DateInterval('P1D')));
            } else {
                continue;
            }

            $condText = preg_replace('/^\s*(?:PROB30|PROB40)?\s*(?:BECMG|TEMPO|INTER)?\s*(?:FM\d{6})?\b/i', '', $piece);
            $update = self::parseCondition($condText);
            $target = $current->merged($update);
            $effectiveKind = str_contains($piece, 'FM') ? 'FM' : $kind;

            $groups[] = new Group($effectiveKind, $gs, $ge, $target, clone $current);
            if (in_array($effectiveKind, ['BECMG', 'FM'])) {
                $current = $target;
            }
        }

        return new TafData($raw, $issued, $start, $end, $groups, str_contains($raw, 'COR'), str_contains($raw, 'AMD'), !$hasHeader);
    }

    public static function parseObservation(string $raw, ?DateTime $anchor = null): ?ObservationData
    {
        $raw = trim(preg_replace('/\s+/', ' ', str_replace('=', '', $raw)));
        if (!preg_match('/\b(METAR|SPECI)(?:\s+COR)?\s+WITN\s+(\d{2})(\d{2})(\d{2})Z\b/i', $raw, $m)) {
            return null;
        }

        $at = self::resolveDate((int)$m[2], (int)$m[3], (int)$m[4], $anchor ?? new DateTime('now', new DateTimeZone('UTC')));
        $cond = self::parseCondition($raw);

        return new ObservationData($raw, $at, strtoupper($m[1]), $cond, str_contains($raw, 'COR'));
    }
}