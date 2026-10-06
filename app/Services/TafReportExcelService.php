<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TafReportExcelService
{
    public function generate(Carbon $start, array $tafs, array $observations): string
    {
        $templatePath = storage_path('app/templates/Verifikasi_Taf.xlsx');
        
        if (!file_exists($templatePath)) {
            throw new \Exception("File template 'storage/app/templates/Verifikasi_Taf.xlsx' belum tersedia.");
        }

        $spreadsheet = IOFactory::load($templatePath);
        $daysInMonth = $start->daysInMonth;

        // 1. Hapus sheet tanggal yang melebihi jumlah hari dalam bulan tersebut (misal: hapus tanggal 31 di bulan September)
        for ($day = $daysInMonth + 1; $day <= 31; $day++) {
            $sheetToRemove = $spreadsheet->getSheetByName((string)$day);
            if ($sheetToRemove !== null) {
                $sheetIndex = $spreadsheet->getIndex($sheetToRemove);
                $spreadsheet->removeSheetByIndex($sheetIndex);
            }
        }

        // 2. Buat kloning sheet hanya jika sheet tanggal tersebut belum ada di template
        $baseSheet = $spreadsheet->getSheetByName('1');
        for ($day = 2; $day <= $daysInMonth; $day++) {
            if ($spreadsheet->getSheetByName((string)$day) === null && $baseSheet !== null) {
                $cloned = clone $baseSheet;
                $cloned->setTitle((string)$day);
                $spreadsheet->addSheet($cloned);
            }
        }

        // 3. Update judul pada sheet rekap
        $recapSheet = $spreadsheet->getSheetByName('rekap 1 bulan');
        if ($recapSheet) {
            $recapSheet->setCellValue('A2', "VERIFIKASI TAFOR BULAN " . strtoupper($start->translatedFormat('F Y')) . " - WITN MAIMUN SALEH SABANG");
        }

        $fileName = 'Verifikasi_TAF_' . $start->format('Y_m') . '.xlsx';
        $outputPath = storage_path('app/public/' . $fileName);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($outputPath);

        return $fileName;
    }
}