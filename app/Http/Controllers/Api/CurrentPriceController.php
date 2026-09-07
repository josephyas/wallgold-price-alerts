<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Alerts\Contracts\AlertIndex;
use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentPriceResource;
use Illuminate\Http\Response;

final class CurrentPriceController extends Controller
{
    public function __invoke(AlertIndex $index): CurrentPriceResource
    {
        $current = $index->currentPrice();

        abort_if($current === null, Response::HTTP_SERVICE_UNAVAILABLE, 'No gold price has been observed yet.');

        return CurrentPriceResource::make($current);
    }
}
