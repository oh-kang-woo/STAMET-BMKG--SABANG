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
    protected TafVerifierService $verifier;

    public function __construct(TafVerifierService $verifier)
    {
        $this->verifier = $verifier;
    }

    public function generate(Carbon $start, array $tafs, array $observations): string
    {
        $templatePath = storage_path('app/templates/Verifikasi_Taf.xlsx');
        if (!file_exists($templatePath)) {
            $templatePath = base_path('Template Verif TAF.xlsx');
        }

        if (!file_exists($templatePath)) {
            throw new \Exception("File template 'Verifikasi_Taf.xlsx' atau 'Template Verif TAF.xlsx' tidak ditemukan.");
        }

        $spreadsheet = IOFactory::load($templatePath);
        $daysInMonth = $start->daysInMonth;

        // Hapus sheet tanggal yang melebihi jumlah hari dalam bulan
        for ($day = $daysInMonth + 1; $day <= 31; $day++) {
            $sheet = $spreadsheet->getSheetByName((string)$day);
            if ($sheet !== null) {
                $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($sheet));
            }
        }

        // Isi data harian
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $sheet = $spreadsheet->getSheetByName((string)$day);
            if ($sheet !== null) {
                $this->processDaySheet($sheet, $start->year, $start->month, $day, $tafs, $observations);
            }
        }

        // Update Judul Rekap Bulanan
        $rekapSheet = $spreadsheet->getSheetByName('rekap 1 bulan');
        if ($rekapSheet !== null) {
            $rekapSheet->setCellValue(
                'A2',
                "VERIFIKASI TAFOR BULAN " . strtoupper($start->translatedFormat('F Y')) . " - WITN MAIMUN SALEH SABANG"
            );
        }

        $fileName = 'Verifikasi_TAF_' . $start->format('Y_m') . '.xlsx';
        $outputPath = storage_path('app/public/' . $fileName);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($outputPath);

        return $fileName;
    }

    protected function processDaySheet(
        Worksheet $sheet,
        int $year,
        int $month,
        int $day,
        array $tafList,
        array $obsList
    ): void {
        $sheet->setCellValue('B3', 'BULAN : ' . sprintf('%02d', $month));
        $sheet->setCellValue('E3', 'TAHUN : ' . $year);

        // Pemetaan 4 Blok Utama (Start Row untuk jam 00, 06, 12, 18 UTC)
        $blockStartRows = [
            0  => 10,  // 00:00 - 05:30 UTC
            6  => 73,  // 06:00 - 11:30 UTC
            12 => 139, // 12:00 - 17:30 UTC
            18 => 204, // 18:00 - 23:30 UTC
        ];

        foreach ($blockStartRows as $startHour => $baseRow) {
            for ($slot = 0; $slot < 12; $slot++) { // 12 slot @ 30 menit = 6 jam
                $row = $baseRow + $slot;
                $slotMinutes = ($startHour * 60) + ($slot * 30);
                $hour = intdiv($slotMinutes, 60);
                $minute = $slotMinutes % 60;

                $targetTime = new DateTime(
                    sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute),
                    new DateTimeZone('UTC')
                );

                $activeTaf = $this->findActiveTaf($tafList, $targetTime);
                $activeObs = $this->findObservation($obsList, $targetTime);

                if ($activeTaf || $activeObs) {
                    $this->writeRow($sheet, $row, $activeTaf, $activeObs, $targetTime);
                }
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
            if ($diff <= 900) { // Toleransi 15 menit dari jam slot
                return $obs;
            }
        }
        return null;
    }

    protected function writeRow(
        Worksheet $sheet,
        int $row,
        ?TafData $taf,
        ?ObservationData $obs,
        DateTime $targetTime
    ): void {
        if ($taf) {
            $groups = $this->verifier->matchingGroups($taf, $targetTime);
            $group = end($groups);
            $cond = $group->condition;

            // Tulis Change Group & Parameter TAF
            $sheet->setCellValue("D{$row}", $group->kind);
            $sheet->setCellValue("F{$row}", $cond->windDir ?? '');
            $sheet->setCellValue("G{$row}", $cond->windSpeed ?? '');
            $sheet->setCellValue("H{$row}", $cond->gust ? 'GUST' : 0);
            $sheet->setCellValue("I{$row}", $cond->visibility ?? '');
            $sheet->setCellValue("J{$row}", $cond->precipCode ?? 'NONE');
            $sheet->setCellValue("K{$row}", $cond->cloud ?? '');
            $sheet->setCellValue("L{$row}", $cond->ceilingFt ?? '');
        }

        if ($obs) {
            $a = $obs->condition;
            // Tulis METAR Raw & Nilai METAR Aktual
            $sheet->setCellValue("M{$row}", $obs->raw);
            $sheet->setCellValue("N{$row}", $a->windDir ?? '');
            $sheet->setCellValue("P{$row}", $a->windSpeed ?? '');
            $sheet->setCellValue("R{$row}", $a->gust ? 'GUST' : 0);
            $sheet->setCellValue("T{$row}", $a->visibility ?? '');
            $sheet->setCellValue("V{$row}", $a->precipCode ?? 'NONE');
            $sheet->setCellValue("X{$row}", $a->cloud ?? '');
            $sheet->setCellValue("Z{$row}", $a->ceilingFt ?? '');

            // Hitung & Tulis Skor Evaluasi (Kolom O, Q, S, U, W, Y, AA)
            if ($taf) {
                $groups = $this->verifier->matchingGroups($taf, $targetTime);
                $expectedGroup = end($groups);
                $scores = $this->verifier->calculateScore($expectedGroup->condition, $a, $obs->raw);

                $scoreCols = [
                    'O' => 'wind_dir',
                    'Q' => 'wind_speed',
                    'S' => 'gust',
                    'U' => 'visibility',
                    'W' => 'precip',
                    'Y' => 'cloud',
                    'AA' => 'ceiling'
                ];

                foreach ($scoreCols as $col => $scoreKey) {
                    $sheet->setCellValue("{$col}{$row}", $scores[$scoreKey]);
                }
            }
        }
    }
}