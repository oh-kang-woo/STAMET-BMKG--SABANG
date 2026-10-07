<?php

namespace App\Services;

use App\DTOs\Taf\Condition;
use App\DTOs\Taf\Group;
use App\DTOs\Taf\TafData;
use DateTimeInterface;

class TafVerifierService
{
    public function visibilityClass(?int $v): ?int
    {
        if ($v === null) return null;
        if ($v < 800) return 1;
        if ($v < 1500) return 2;
        if ($v < 3000) return 3;
        if ($v <= 5000) return 4;
        return 5;
    }

    public function rainClass(?string $code): string
    {
        if (!$code || $code === 'NONE') return 'NONE';
        $code = strtoupper($code);
        if (str_starts_with($code, '+')) return 'HEAVY';
        if (str_starts_with($code, '-')) return 'LIGHT';
        return 'MODERATE';
    }

    public function calculateScore(Condition $expected, Condition $actual, string $rawMetar): array
    {
        // 1. Wind Direction
        $windDir = 0;
        if ($expected->windDir !== null && $actual->windDir !== null) {
            if ($expected->windDir === 999 && $actual->windDir === 999) {
                $windDir = 1;
            } elseif ($expected->windDir === 999) {
                $windDir = (($actual->windSpeed ?? 999) < 10 || str_contains($rawMetar, 'TS') || str_contains($rawMetar, 'CB')) ? 1 : 0;
            } elseif ($actual->windDir === 999) {
                $windDir = (($expected->windSpeed ?? 999) < 10 && ($actual->windSpeed ?? 999) < 10) ? 1 : 0;
            } else {
                $delta = abs($expected->windDir - $actual->windDir);
                $delta = min($delta, 360 - $delta);
                $windDir = ($delta <= 60 || ($actual->windSpeed ?? 999) < 10) ? 1 : 0;
            }
        }

        // 2. Wind Speed
        $windSpeed = ($expected->windSpeed !== null && $actual->windSpeed !== null && abs($expected->windSpeed - $actual->windSpeed) <= 10) ? 1 : 0;

        // 3. Gust
        $gust = ($expected->gust === $actual->gust) ? 1 : 0;

        // 4. Visibility
        $vis = ($expected->visibility !== null && $actual->visibility !== null && $this->visibilityClass($expected->visibility) === $this->visibilityClass($actual->visibility)) ? 1 : 0;

        // 5. Precipitation
        $eRain = $this->rainClass($expected->precipCode);
        $aRain = $this->rainClass($actual->precipCode);
        $precip = match ($eRain) {
            'HEAVY' => ($aRain === 'HEAVY') ? 1 : 0,
            'MODERATE' => in_array($aRain, ['MODERATE', 'HEAVY']) ? 1 : 0,
            default => in_array($aRain, ['NONE', 'LIGHT']) ? 1 : 0,
        };

        // 6. Cloud Amount
        $cloud = 0;
        if (($actual->ceilingFt ?? 0) > 4921) {
            $cloud = 1;
        } elseif ($expected->cloud && $actual->cloud) {
            $low = ['SKC', 'FEW', 'SCT'];
            $high = ['BKN', 'OVC'];
            $cloud = ((in_array($expected->cloud, $low) && in_array($actual->cloud, $low)) ||
                      (in_array($expected->cloud, $high) && in_array($actual->cloud, $high))) ? 1 : 0;
        }

        // 7. Ceiling Base
        if ($expected->ceilingFt === null || $actual->ceilingFt === null) {
            $ceiling = 0;
        } elseif ($expected->ceilingFt < 1000) {
            $ceiling = (abs($expected->ceilingFt - $actual->ceilingFt) <= 100) ? 1 : 0;
        } else {
            $ceiling = (abs($expected->ceilingFt - $actual->ceilingFt) <= 0.30 * $expected->ceilingFt) ? 1 : 0;
        }

        return [
            'wind_dir' => $windDir,
            'wind_speed' => $windSpeed,
            'gust' => $gust,
            'visibility' => $vis,
            'precip' => $precip,
            'cloud' => $cloud,
            'ceiling' => $ceiling,
        ];
    }

    public function matchingGroups(TafData $taf, DateTimeInterface $at): array
    {
        if ($at < $taf->validStart || $at > $taf->validEnd) {
            return [$taf->groups[0]];
        }

        $permanent = array_filter($taf->groups, fn($g) => in_array($g->kind, ['GENERAL', 'BECMG', 'FM']) && $g->start <= $at);
        usort($permanent, fn($a, $b) => $b->start <=> $a->start);
        $baseGroup = !empty($permanent) ? array_values($permanent)[0] : $taf->groups[0];

        $base = ($baseGroup->kind === 'BECMG' && $at < $baseGroup->end)
            ? $baseGroup
            : new Group($baseGroup->kind === 'GENERAL' ? 'GENERAL' : $baseGroup->kind, $baseGroup->start, $taf->validEnd, $baseGroup->condition, $baseGroup->previous);

        $transient = array_filter($taf->groups, fn($g) => in_array($g->kind, ['TEMPO', 'PROB30 TEMPO', 'PROB40 TEMPO', 'PROB30', 'PROB40', 'INTER']) && $g->start <= $at && $at < $g->end);
        if (!empty($transient)) {
            usort($transient, fn($a, $b) => $b->start <=> $a->start);
            return [$base, array_values($transient)[0]];
        }

        return [$base];
    }
}