<?php

namespace App\Services;

use App\Helpers\AwosDataHelper;
use App\Models\DataAwos;
use App\Models\DataSinoptik;
use Carbon\Carbon;

class MetarComposer
{
    /**
     * Ketinggian Stasiun Meteorologi Sabang (meter dpl)
     */
    protected const STATION_ELEVATION_M = 126.0;

    public static function composeMetarMessage(DataSinoptik $dataSinoptik)
    {
        $headingTime = self::formatHeadingTime($dataSinoptik);
        $wind = self::formatWind($dataSinoptik);
        $petirHujan = AwosDataHelper::getSandiPetirHujan($dataSinoptik->present_weather_ww);
        $visibility = AwosDataHelper::getSandiVisibility($dataSinoptik->visibility_vv);
        $sandiCloud = AwosDataHelper::getSandiCloud(
            $dataSinoptik->cloud_layer_1_type_c,
            $dataSinoptik->cloud_layer_1_height_m_hshs,
            $dataSinoptik->cloud_layer_1_amt_oktas_ns,
            $dataSinoptik->cloud_layer_2_type_c,
            $dataSinoptik->cloud_layer_2_height_m_hshs,
            $dataSinoptik->cloud_layer_2_amt_oktas_ns
        );
        $temperature = self::roundValue($dataSinoptik->temp_drybulb_c_tttttt);
        $dewPoint = self::roundValue($dataSinoptik->temp_dewpoint_c_tdtdtd);

        // Gunakan QNH AWOS hanya jika Bridge ON; jika OFF kembali ke logika bawaan (QFF)
        if (app(AwosToAwsBridgeService::class)->isEnabled()) {
            $qnhValue = self::resolveQnh($dataSinoptik);
            $pressureQ = $qnhValue !== null ? (int) floor($qnhValue) : round($dataSinoptik->pressure_qff_mb_derived);
        } else {
            $pressureQ = round($dataSinoptik->pressure_qff_mb_derived);
        }

        $nosig = " NOSIG";
        $stnId = setting('station_icao_id');
        $metarSaId = setting('bmkgsatu_heading_list')['metar'];

        // Header METAR
        $heading = $metarSaId . " " . $stnId . " " . $headingTime . "\n";

        // METAR Message
        $metarMessage = "METAR {$stnId} {$headingTime}Z {$wind}KT {$visibility}{$petirHujan} "
                      . "{$sandiCloud} {$temperature}/{$dewPoint} Q" . str_pad($pressureQ, 4, "0", STR_PAD_LEFT) . "{$nosig}=";

        return $heading . $metarMessage;
    }

    /**
     * Ambil nilai QNH untuk METAR (hanya dipanggil saat Bridge ON):
     * 1. Kolom qnh_1m_mb dari DataAwos pada menit observasi (:50 jam sebelumnya)
     * 2. Kolom qnh_1m_mb terbaru dari DataAwos dalam rentang 15 menit menjelang jam METAR
     * 3. Konversi QFE -> QNH standar atmosfer ICAO (elevasi 126 m) jika AWOS kosong
     */
    protected static function resolveQnh(DataSinoptik $dataSinoptik): ?float
    {
        $sinoptikTime = Carbon::parse($dataSinoptik->data_timestamp);
        $referenceObsTime = $sinoptikTime->copy()->subHour()->setMinutes(50)->setSeconds(0);

        // 1. Cek tepat pada menit observasi :50
        $awos = DataAwos::where('data_timestamp', $referenceObsTime->format('Y-m-d H:i:s'))
            ->whereNotNull('qnh_1m_mb')
            ->first();

        // 2. Fallback rentang :45 jam sebelumnya s/d jam observasi
        if (!$awos) {
            $awos = DataAwos::whereBetween('data_timestamp', [
                $sinoptikTime->copy()->subHour()->setMinutes(45)->setSeconds(0)->format('Y-m-d H:i:s'),
                $sinoptikTime->format('Y-m-d H:i:s'),
            ])
                ->whereNotNull('qnh_1m_mb')
                ->orderByDesc('data_timestamp')
                ->first();
        }

        if ($awos && $awos->qnh_1m_mb !== null) {
            return (float) $awos->qnh_1m_mb;
        }

        // 3. Fallback konversi QFE -> QNH menggunakan rumus atmosfer standar ICAO
        if ($dataSinoptik->pressure_qfe_mb_derived !== null) {
            $h = (float) setting('station_elevation', self::STATION_ELEVATION_M);
            $qfe = (float) $dataSinoptik->pressure_qfe_mb_derived;
            $qnh = $qfe * pow(1 - ((0.0065 * $h) / 288.15), -5.25588);

            return round($qnh, 1);
        }

        return null;
    }

    protected static function formatHeadingTime(DataSinoptik $dataSinoptik)
    {
        return Carbon::parse($dataSinoptik->data_timestamp)->format('dHi');
    }

    protected static function formatWind(DataSinoptik $dataSinoptik)
    {
        return str_pad($dataSinoptik->wind_dir_deg_dd, 3, "0", STR_PAD_LEFT) .
               str_pad($dataSinoptik->wind_speed_ff, 2, "0", STR_PAD_LEFT);
    }

    protected static function roundValue($value)
    {
        return floor($value + 0.5);
    }
}