<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;

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

        $baseSheet = $spreadsheet->getSheetByName('1');
        for ($day = 2; $day <= $daysInMonth; $day++) {
            $cloned = clone $baseSheet;
            $cloned->setTitle((string)$day);
            $spreadsheet->addSheet($cloned);
        }

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