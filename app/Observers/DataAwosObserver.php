<?php

namespace App\Observers;

use App\Models\DataAwos;
use App\Services\AwosToAwsBridgeService;

class DataAwosObserver
{
    public function created(DataAwos $dataAwos): void
    {
        app(AwosToAwsBridgeService::class)->syncRecord($dataAwos);
    }
}