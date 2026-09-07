<?php

namespace App\Http\Controllers\Api;

use App\Domain\PriceAlert\Actions\CreatePriceAlertAction;
use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceAlertRequest;
use Illuminate\Http\JsonResponse;

class PriceAlertController extends Controller
{
    public function store(StorePriceAlertRequest $request, CreatePriceAlertAction $action): JsonResponse
    {
        $alert = $action->handle(
            userId: $request->user()->id,
            targetPrice: $request->input('target_price'),
            direction: $request->enum('direction', AlertDirection::class),
        );

        return response()->json([
            'data' => [
                'target_price' => $alert->target_price,
                'direction' => $alert->direction->value,
                'status' => $alert->status->value,
            ],
        ], 201);
    }
}
