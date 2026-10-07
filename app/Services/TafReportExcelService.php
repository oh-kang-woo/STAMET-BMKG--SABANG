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
            throw new \Exception("File template verifikasi TAF 'Verifikasi_Taf.xlsx' atau 'Template Verif TAF.xlsx' tidak ditemukan.");
        }

        $spreadsheet = IOFactory::load($templatePath);

        $year = $start->year;
        $month = $start->month;
        $daysInMonth = $start->daysInMonth;

        // 1. Hapus sheet tanggal melebihi batas hari bulan ini
        for ($day = $daysInMonth + 1; $day <= 31; $day++) {
            $sheetName = (string)$day;
            $sheetToRemove = $spreadsheet->getSheetByName($sheetName);
            if ($sheetToRemove !== null) {
                $sheetIndex = $spreadsheet->getIndex($sheetToRemove);
                $spreadsheet->removeSheetByIndex($sheetIndex);
            }
        }

        // 2. Isikan data verifikasi harian
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $sheetName = (string)$day;
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet !== null) {
                $this->processDaySheet($sheet, $year, $month, $day, $tafs, $observations);
            }
        }

        // 3. Update Judul Sheet Rekap
        $rekapSheet = $spreadsheet->getSheetByName('rekap 1 bulan');
        if ($rekapSheet !== null) {
            $rekapSheet->setCellValue(
                'A2',
                "VERIFIKASI TAFOR BULAN " . strtoupper($start->translatedFormat('F Y')) . " - WITN MAIMUN SALEH SABANG"
            );
        }

        // 4. Simpan File Output
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

        $targetBlocks = [0, 6, 12, 18]; 
        
        foreach ($targetBlocks as $index => $targetHour) {
            $targetTime = new DateTime(
                sprintf('%04d-%02d-%02d %02d:00:00', $year, $month, $day, $targetHour),
                new DateTimeZone('UTC')
            );
            
            $activeTaf = $this->findActiveTaf($tafList, $targetTime);
            $activeObs = $this->findObservation($obsList, $targetTime);

            // Tulis ke sheet Excel meskipun salah satu data TAF / METAR kosong
            $this->writeBlockToSheet($sheet, $index, $activeTaf, $activeObs, $targetTime);
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

    protected function writeBlockToSheet(
        Worksheet $sheet, 
        int $blockIndex, 
        ?TafData $taf, 
        ?ObservationData $obs, 
        DateTime $targetTime
    ): void {
        $baseRow = 11 + ($blockIndex * 6);

        if ($taf) {
            $sheet->setCellValue("D" . $baseRow, $taf->raw);
        }

        if ($obs) {
            $sheet->setCellValue("M" . $baseRow, $obs->raw);
        }

        // Jika TAF dan METAR lengkap, lakukan evaluasi SOP
        if ($taf && $obs) {
            $groups = $this->verifier->matchingGroups($taf, $targetTime);
            $expectedGroup = end($groups); // Ambil grup kondisi aktif (e.g. TEMPO/BECMG/GENERAL)
            
            $scores = $this->verifier->calculateScore($expectedGroup->condition, $obs->condition, $obs->raw);

            $cols = [
                'F' => $scores['wind_dir'],
                'G' => $scores['wind_speed'],
                'H' => $scores['gust'],
                'I' => $scores['visibility'],
                'J' => $scores['precip'],
                'K' => $scores['cloud'],
                'L' => $scores['ceiling'],
            ];

            foreach ($cols as $col => $val) {
                $sheet->setCellValue($col . $baseRow, $val);
            }
        }
    }
}