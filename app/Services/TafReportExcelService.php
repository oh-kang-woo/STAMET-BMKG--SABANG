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

        // 1. Hapus sheet tanggal yang melebihi jumlah hari dalam bulan tersebut
        for ($day = $daysInMonth + 1; $day <= 31; $day++) {
            $sheetToRemove = $spreadsheet->getSheetByName((string)$day);
            if ($sheetToRemove !== null) {
                $sheetIndex = $spreadsheet->getIndex($sheetToRemove);
                $spreadsheet->removeSheetByIndex($sheetIndex);
            }
        }

        // 2. Buat kloning sheet jika belum ada
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

        // Struktur 4 blok slot waktu (per 30 menit) di setiap sheet harian
        $blocks = [
            ['start_hour' => 0,  'start_row' => 11,  'rows_count' => 25], // 00:00 - 12:00 UTC
            ['start_hour' => 6,  'start_row' => 56,  'rows_count' => 25], // 06:00 - 18:00 UTC
            ['start_hour' => 12, 'start_row' => 102, 'rows_count' => 25], // 12:00 - 24:00 UTC
            ['start_hour' => 18, 'start_row' => 147, 'rows_count' => 25], // 18:00 - 06:00 (+1 hr)
        ];

        // Mapping data METAR/SPECI berdasarkan waktu (Y-m-d H:i)
        $obsMap = [];
        foreach ($observations as $obs) {
            if ($obs && isset($obs->at)) {
                $key = $obs->at->format('Y-m-d H:i');
                $obsMap[$key] = $obs;
            }
        }

        // 4. Isi data TAF dan METAR ke tiap sheet tanggal
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $sheet = $spreadsheet->getSheetByName((string)$day);
            if (!$sheet) {
                continue;
            }

            $sheet->setCellValue('B3', "BULAN : " . strtoupper($start->translatedFormat('F')));
            $sheet->setCellValue('E3', "TAHUN : " . $start->format('Y'));

            $currentDate = $start->copy()->day($day);

            foreach ($blocks as $block) {
                for ($i = 0; $i < $block['rows_count']; $i++) {
                    $row = $block['start_row'] + $i;
                    $minutesOffset = $i * 30;
                    
                    $slotTime = $currentDate->copy()->startOfDay()->addHours($block['start_hour'])->addMinutes($minutesOffset);

                    // A. Tulis Teks METAR/SPECI ke Kolom M (DATA METAR)
                    $obsKey = $slotTime->format('Y-m-d H:i');
                    if (isset($obsMap[$obsKey])) {
                        $sheet->setCellValue("M{$row}", $obsMap[$obsKey]->raw);
                    }

                    // B. Tulis Data Prakiraan TAF ke Kolom D - L
                    $matchedGroup = null;
                    foreach ($tafs as $taf) {
                        if (!$taf || empty($taf->groups)) {
                            continue;
                        }
                        foreach ($taf->groups as $group) {
                            if ($slotTime->getTimestamp() >= $group->start->getTimestamp() && $slotTime->getTimestamp() < $group->end->getTimestamp()) {
                                $matchedGroup = $group;
                                break 2;
                            }
                        }
                    }

                    if ($matchedGroup) {
                        $cond = $matchedGroup->condition;
                        $sheet->setCellValue("D{$row}", $matchedGroup->kind);
                        $sheet->setCellValue("E{$row}", $matchedGroup->start->format('d/H') . '-' . $matchedGroup->end->format('d/H'));
                        $sheet->setCellValue("F{$row}", $cond->windDir ?? '');
                        $sheet->setCellValue("G{$row}", $cond->windSpeed ?? '');
                        $sheet->setCellValue("H{$row}", ($cond->gust ?? false) ? 'GUST' : 0);
                        $sheet->setCellValue("I{$row}", $cond->visibility ?? '');
                        $sheet->setCellValue("J{$row}", ($cond->precipCode && $cond->precipCode !== 'NONE') ? $cond->precipCode : 0);
                        $sheet->setCellValue("K{$row}", $cond->cloud ?? '');
                        $sheet->setCellValue("L{$row}", $cond->ceilingFt ?? '');
                    }
                }
            }
        }

        $fileName = 'Verifikasi_TAF_' . $start->format('Y_m') . '.xlsx';
        $outputPath = storage_path('app/public/' . $fileName);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save($outputPath);

        return $fileName;
    }
}