<?php

namespace App\Services;

use App\DTOs\Taf\TafData;
use App\DTOs\Taf\ObservationData;
use DateTime;
use DateTimeZone;
use DateInterval;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TafReportExcelService
{
    protected TafParserService $parser;

    public function __construct(TafParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Menghasilkan file Excel laporan verifikasi TAF bulanan.
     */
    public function generateMonthlyReport(
        int $year,
        int $month,
        array $rawTafs,
        array $rawObservations,
        string $templatePath,
        string $outputPath
    ): string {
        $spreadsheet = IOFactory::load($templatePath);
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // Parse semua data TAF dan METAR/SPECI
        $tafList = [];
        foreach ($rawTafs as $raw) {
            $parsed = $this->parser->parseTaf($raw);
            if ($parsed) $tafList[] = $parsed;
        }

        $obsList = [];
        foreach ($rawObservations as $raw) {
            $parsed = $this->parser->parseObservation($raw);
            if ($parsed) $obsList[] = $parsed;
        }

        // Hapus sheet tanggal yang melebihi jumlah hari dalam bulan tersebut
        for ($d = $daysInMonth + 1; $d <= 31; $d++) {
            $sheetName = sprintf('%02d', $d);
            if ($spreadsheet->sheetExists($sheetName)) {
                $sheetIndex = $spreadsheet->getIndex($spreadsheet->getSheetByName($sheetName));
                $spreadsheet->removeSheetByIndex($sheetIndex);
            }
        }

        // Isikan data verifikasi harian (Sheet 01 - 28/30/31)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $sheetName = sprintf('%02d', $day);
            if ($spreadsheet->sheetExists($sheetName)) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                $this->processDaySheet($sheet, $year, $month, $day, $tafList, $obsList);
            }
        }

        // Update Judul di Sheet Rekap 1 Bulan
        if ($spreadsheet->sheetExists('rekap 1 bulan')) {
            $rekapSheet = $spreadsheet->getSheetByName('rekap 1 bulan');
            $monthNames = [
                1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
                5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
                9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
            ];
            $title = "REKAPITULASI HASIL EVALUASI VERIFIKASI TAF BULAN " . $monthNames[$month] . " " . $year;
            $rekapSheet->setCellValue('A2', $title);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);

        return $outputPath;
    }

    /**
     * Memproses dan mengisi data verifikasi harian ke dalam sheet.
     */
    protected function processDaySheet(
        Worksheet $sheet,
        int $year,
        int $month,
        int $day,
        array $tafList,
        array $obsList
    ): void {
        $targetBlocks = [0, 6, 12, 18]; // UTC hours
        
        foreach ($targetBlocks as $index => $targetHour) {
            $targetTime = new DateTime(sprintf('%04d-%02d-%02d %02d:00:00', $year, $month, $day, $targetHour), new DateTimeZone('UTC'));
            
            // Cari TAF yang berlaku untuk jam target ini
            $activeTaf = $this->findActiveTaf($tafList, $targetTime);
            
            // Cari Observation (METAR/SPECI) pada jam target
            $activeObs = $this->findObservation($obsList, $targetTime);

            if ($activeTaf && $activeObs) {
                $scores = $this->evaluateSop($activeTaf, $activeObs, $targetTime);
                $this->writeBlockToSheet($sheet, $index, $activeTaf, $activeObs, $scores);
            }
        }
    }

    protected function findActiveTaf(array $tafList, DateTime $targetTime): ?TafData
    {
        foreach ($tafList as $taf) {
            if ($taf->validStart <= $targetTime && $targetTime <= $taf->validEnd) {
                return $taf;
            }
        }
        return null;
    }

    protected function findObservation(array $obsList, DateTime $targetTime): ?ObservationData
    {
        foreach ($obsList as $obs) {
            $diff = abs($obs->at->getTimestamp() - $targetTime->getTimestamp());
            if ($diff <= 1800) { // Toleransi 30 menit
                return $obs;
            }
        }
        return null;
    }

    /**
     * Evaluasi 7 Parameter SOP: A, B1, B2, C, D, E, F
     */
    protected function evaluateSop(TafData $taf, ObservationData $obs, DateTime $targetTime): array
    {
        // Cari kondisi TAF yang berlaku pada targetTime
        $group = $taf->groups[0];
        foreach ($taf->groups as $g) {
            if ($g->start <= $targetTime && $targetTime <= $g->end) {
                $group = $g;
            }
        }

        $e = $group->condition;
        $a = $obs->condition;

        // A. Arah Angin (Delta <= 60 derajat atau kecepatan < 10 kt)
        $windDirOk = 0;
        if ($e->windDir !== null && $a->windDir !== null) {
            $delta = abs($e->windDir - $a->windDir);
            $delta = min($delta, 360 - $delta);
            if ($delta <= 60 || ($a->windSpeed !== null && $a->windSpeed < 10)) {
                $windDirOk = 1;
            }
        }

        // B1. Kecepatan Angin (Selisih <= 10 kt)
        $windSpdOk = ($e->windSpeed !== null && $a->windSpeed !== null && abs($e->windSpeed - $a->windSpeed) <= 10) ? 1 : 0;

        // B2. Gust
        $gustOk = ($e->gust === $a->gust) ? 1 : 0;

        // C. Jarak Pandang (Visibility Class sama)
        $visOk = ($e->visibility !== null && $a->visibility !== null && $this->visClass($e->visibility) === $this->visClass($a->visibility)) ? 1 : 0;

        // D. Presipitasi / Cuaca
        $precipOk = ($e->precipCode === $a->precipCode || ($e->precipCode === 'NONE' && $a->precipCode === 'NONE')) ? 1 : 0;

        // E. Jumlah Awan (Low vs High Cloud)
        $cloudOk = ($e->cloud === $a->cloud) ? 1 : 0;

        // F. Tinggi Dasar Awan / Ceiling
        $ceilingOk = 0;
        if ($e->ceilingFt !== null && $a->ceilingFt !== null) {
            if ($e->ceilingFt < 1000) {
                $ceilingOk = abs($e->ceilingFt - $a->ceilingFt) <= 100 ? 1 : 0;
            } else {
                $ceilingOk = abs($e->ceilingFt - $a->ceilingFt) <= (0.30 * $e->ceilingFt) ? 1 : 0;
            }
        }

        return [$windDirOk, $windSpdOk, $gustOk, $visOk, $precipOk, $cloudOk, $ceilingOk];
    }

    protected function visClass(int $vis): int
    {
        if ($vis < 800) return 1;
        if ($vis < 1500) return 2;
        if ($vis < 3000) return 3;
        if ($vis < 5000) return 4;
        return 5;
    }

    protected function writeBlockToSheet(Worksheet $sheet, int $blockIndex, TafData $taf, ObservationData $obs, array $scores): void
    {
        // Offset baris berdasarkan blok waktu (00, 06, 12, 18 UTC)
        $baseRow = 7 + ($blockIndex * 12);

        // Tulis TAF & OBS Raw
        $sheet->setCellValue("B" . ($baseRow), $taf->raw);
        $sheet->setCellValue("B" . ($baseRow + 3), $obs->raw);

        // Tulis Nilai Skor SOP (1 / 0)
        $cols = ['D', 'E', 'F', 'G', 'H', 'I', 'J'];
        foreach ($scores as $i => $score) {
            $sheet->setCellValue($cols[$i] . ($baseRow + 5), $score);
        }
    }
}