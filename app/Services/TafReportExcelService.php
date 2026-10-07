<?php

namespace App\Services;

use App\DTOs\Taf\TafData;
use App\DTOs\Taf\ObservationData;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TafReportExcelService
{
    protected TafParserService $parser;

    public function __construct(TafParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Entry point yang dipanggil oleh VerifyTafPage.php
     */
    public function generate(Carbon $start, array $tafs, array $observations): string
    {
        // Cari lokasi file template di storage/app/templates/
        $templatePath = storage_path('app/templates/Verifikasi_Taf.xlsx');
        if (!file_exists($templatePath)) {
            $templatePath = base_path('Template Verif TAF.xlsx');
        }

        if (!file_exists($templatePath)) {
            throw new \Exception("File template verifikasi TAF tidak ditemukan.");
        }

        $spreadsheet = IOFactory::load($templatePath);
        
        $year = $start->year;
        $month = $start->month;
        $daysInMonth = $start->daysInMonth;

        // 1. Parse seluruh string TAF dan Observation
        $tafList = [];
        foreach ($tafs as $raw) {
            $parsed = $this->parser->parseTaf($raw, $start->toDateTime());
            if ($parsed) {
                $tafList[] = $parsed;
            }
        }

        $obsList = [];
        foreach ($observations as $raw) {
            $parsed = $this->parser->parseObservation($raw, $start->toDateTime());
            if ($parsed) {
                $obsList[] = $parsed;
            }
        }

        // 2. Hapus sheet tanggal yang melebihi jumlah hari dalam bulan tersebut
        for ($day = $daysInMonth + 1; $day <= 31; $day++) {
            $sheetName = (string)$day;
            if ($spreadsheet->sheetExists($sheetName)) {
                $sheetIndex = $spreadsheet->getIndex($spreadsheet->getSheetByName($sheetName));
                $spreadsheet->removeSheetByIndex($sheetIndex);
            }
        }

        // 3. Isikan data verifikasi harian (Sheet 1 s.d. 28/30/31)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $sheetName = (string)$day;
            if ($spreadsheet->sheetExists($sheetName)) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                $this->processDaySheet($sheet, $year, $month, $day, $tafList, $obsList);
            }
        }

        // 4. Update Judul pada Sheet "rekap 1 bulan"
        if ($spreadsheet->sheetExists('rekap 1 bulan')) {
            $rekapSheet = $spreadsheet->getSheetByName('rekap 1 bulan');
            $rekapSheet->setCellValue(
                'A2',
                "VERIFIKASI TAFOR BULAN " . strtoupper($start->translatedFormat('F Y')) . " - WITN MAIMUN SALEH SABANG"
            );
        }

        // 5. Simpan file ke disk public storage
        $fileName = 'Verifikasi_TAF_' . $start->format('Y_m') . '.xlsx';
        $outputPath = storage_path('app/public/' . $fileName);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($outputPath);

        return $fileName;
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
        $targetBlocks = [0, 6, 12, 18]; // UTC Hours
        
        foreach ($targetBlocks as $index => $targetHour) {
            $targetTime = new DateTime(
                sprintf('%04d-%02d-%02d %02d:00:00', $year, $month, $day, $targetHour),
                new DateTimeZone('UTC')
            );
            
            $activeTaf = $this->findActiveTaf($tafList, $targetTime);
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

        // C. Jarak Pandang (Visibility Class)
        $visOk = ($e->visibility !== null && $a->visibility !== null && $this->visClass($e->visibility) === $this->visClass($a->visibility)) ? 1 : 0;

        // D. Presipitasi / Cuaca
        $precipOk = ($e->precipCode === $a->precipCode || ($e->precipCode === 'NONE' && $a->precipCode === 'NONE')) ? 1 : 0;

        // E. Jumlah Awan
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
        $baseRow = 11 + ($blockIndex * 6);

        $sheet->setCellValue("D" . $baseRow, $taf->raw);
        $sheet->setCellValue("M" . $baseRow, $obs->raw);

        $cols = ['F', 'G', 'H', 'I', 'J', 'K', 'L'];
        foreach ($scores as $i => $score) {
            $sheet->setCellValue($cols[$i] . $baseRow, $score);
        }
    }
}