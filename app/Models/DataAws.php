<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataAws extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->updateWetBulbTemperature();
        });
    }

    private function updateWetBulbTemperature()
    {
        $drybulb = $this->temp_drybulb_c_tttttt;
        $humidity = $this->relative_humidity_pc;
        $dewpoint = $this->temp_dewpoint_c_tdtdtd;

        if ($drybulb !== null && $humidity !== null && $dewpoint !== null) {
            $this->temp_wetbulb_c = $this->calculateTbb($drybulb, $humidity, $dewpoint);
            $this->saveQuietly(); // Save tanpa memicu event model untuk menghindari rekursi
        }
    }

    private function calculateTbb($t, $rh, $td): ?float
    {
        $t = (float) $t;
        $rh = (float) $rh;
        $tbb = (float) $td;

        if (
            !is_finite($t) ||
            !is_finite($rh) ||
            !is_finite($tbb) ||
            $rh < 0 ||
            $rh > 100 ||
            $t <= -237.3 ||
            $tbb <= -237.3
        ) {
            return null;
        }

        for ($i = 0; $i < 1000; $i++) {
            $numerator = (6.11 * pow(10, (7.5 * $tbb) / (237.3 + $tbb))) - (0.7947 * ($t - $tbb));
            $denominator = 6.11 * pow(10, (7.5 * $t) / (237.3 + $t));

            if ($denominator == 0.0 || !is_finite($denominator)) {
                return null;
            }

            $result = ($numerator / $denominator) * 100;

            if (!is_finite($result)) {
                return null;
            }

            if ($result >= $rh) {
                return round($tbb, 1);
            }

            $tbb += 0.1;
        }

        return null;
    }
}