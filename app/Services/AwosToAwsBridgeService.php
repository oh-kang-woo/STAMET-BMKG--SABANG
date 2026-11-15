<?php

namespace App\Services;

use App\Models\DataAwos;
use App\Models\DataAws;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AwosToAwsBridgeService
{
    /**
     * Ketinggian Stasiun Meteorologi Sabang (meter dpl)
     */
    protected const STATION_ELEVATION_M = 126.0;

    /**
     * Cek apakah modul bridge AWOS -> AWS sedang diaktifkan (ON).
     */
    public function isEnabled(): bool
    {
        return filter_var(setting('bridge_awos_to_aws', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Konversi 1 record DataAwos dan simpan ke tabel data_aws.
     */
    public function syncRecord(DataAwos $awos): ?DataAws
    {
        if (!$this->isEnabled() || empty($awos->data_timestamp)) {
            return null;
        }

        try {
            // 1. Kecepatan & Arah Angin (Prioritas sensor 10m, fallback ke 2m)
            $windSpeedKt = $awos->wind_spd_10m_kt ?? $awos->wind_spd_2m_kt;
            $windDir     = $awos->wind_dir_10m ?? $awos->wind_dir_2m;
            $windGustKt  = $awos->wind_peak_10m_kt ?? $awos->wind_gustav_10m_kt;

            // Konversi knot -> m/s (SinoptikDataHelper akan mengalikan kembali dengan 1.94384)
            $windSpeedMs = $windSpeedKt !== null ? round($windSpeedKt / 1.94384, 2) : null;
            $windMaxMs   = $windGustKt !== null ? round($windGustKt / 1.94384, 2) : $windSpeedMs;

            // 2. Tekanan Udara: QFE dari AWOS, QFF dikonversi menggunakan elevasi 126m
            $qfeMb = $awos->qfe_1m_mb;
            $qffMb = $this->convertQfeToQff($qfeMb, $awos->at_5m_c);

            // 3. Curah Hujan per menit (delta dari akumulasi 1 jam AWOS)
            $rainfallMm = $this->calculateMinuteRainfall($awos);

            // 4. Susun Payload sesuai struktur tabel data_aws
            $payload = [
                'device_id'               => $awos->device_id ?? 'AWOS-BRIDGE',
                'wind_dir_deg_dd'         => $windDir !== null ? (int) round($windDir) : null,
                'wind_speed_ff'           => $windSpeedMs,
                'wind_speed_ff_maks'      => $windMaxMs,
                'pressure_qfe_mb_derived' => $qfeMb,
                'pressure_qff_mb_derived' => $qffMb,
                'temp_drybulb_c_tttttt'   => $awos->at_5m_c,
                'temp_dewpoint_c_tdtdtd'  => $awos->dp_5m_c,
                'relative_humidity_pc'    => $awos->rh_5m !== null ? (int) round($awos->rh_5m) : null,
                'rainfall_last_mm'        => $rainfallMm,
                // temp_wetbulb_c otomatis dihitung oleh DataAws::booted() saat record disimpan
            ];

            // Penanganan defensif untuk inkonsistensi nama kolom suhu min/max di schema AWS
            if (Schema::hasColumn('data_aws', 'temp_max_c_txtxtx')) {
                $payload['temp_max_c_txtxtx'] = $awos->at_5m_c;
            } elseif (Schema::hasColumn('data_aws', 'temp_min_c_txtxtx')) {
                $payload['temp_min_c_txtxtx'] = $awos->at_5m_c;
            }

            if (Schema::hasColumn('data_aws', 'temp_min_c_tntntn')) {
                $payload['temp_min_c_tntntn'] = $awos->at_5m_c;
            } elseif (Schema::hasColumn('data_aws', 'temp_max_c_tntntn')) {
                $payload['temp_max_c_tntntn'] = $awos->at_5m_c;
            }

            $aws = DataAws::updateOrCreate(
                ['data_timestamp' => $awos->data_timestamp],
                $payload
            );

            Log::info('[AWOS-to-AWS Bridge] Synced successfully', [
                'awos_id'        => $awos->id,
                'aws_id'         => $aws->id,
                'data_timestamp' => $awos->data_timestamp,
            ]);

            return $aws;
        } catch (\Throwable $e) {
            Log::error('[AWOS-to-AWS Bridge] Failed', [
                'awos_id'        => $awos->id,
                'data_timestamp' => $awos->data_timestamp,
                'exception'      => get_class($e),
                'message'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Rumus reduksi tekanan ke permukaan laut:
     * QFF = QFE * (1 + (0.0065 * h) / (T + 273.15)) ^ 5.257
     */
    public function convertQfeToQff(?float $qfeMb, ?float $tempAirC): ?float
    {
        if ($qfeMb === null || $tempAirC === null) {
            return null;
        }

        if (!is_finite($qfeMb) || !is_finite($tempAirC) || $tempAirC <= -273.15) {
            throw new \InvalidArgumentException('Data QFE atau temperatur AWOS tidak valid.');
        }

        $h = (float) setting('station_elevation', self::STATION_ELEVATION_M);

        $factor = 1 + ((0.0065 * $h) / ($tempAirC + 273.15));
        $qff    = $qfeMb * pow($factor, 5.257);

        return round($qff, 1);
    }

    /**
     * Menghitung penambahan curah hujan dalam 1 menit terakhir
     * agar agregasi SUM(rainfall_last_mm) 3-jam di SinoptikDataHelper tetap akurat.
     */
    protected function calculateMinuteRainfall(DataAwos $current): float
    {
        if ($current->pp_1h_mm === null) {
            return 0.0;
        }

        $prevTime = Carbon::parse($current->data_timestamp)->subMinute()->format('Y-m-d H:i:s');
        $prevAwos = DataAwos::where('data_timestamp', $prevTime)
            ->when($current->device_id, fn ($q) => $q->where('device_id', $current->device_id))
            ->first();

        if (!$prevAwos || $prevAwos->pp_1h_mm === null) {
            return 0.0;
        }

        $delta = $current->pp_1h_mm - $prevAwos->pp_1h_mm;

        if ($delta < 0) {
            return round(max(0.0, (float) $current->pp_1h_mm), 2);
        }

        return round($delta, 2);
    }
}