<?php

namespace App\Http\Controllers\Api;

use App\Actions\PriceAlert\CreatePriceAlertAction;
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
            direction: $request->string('direction')->toString(),
        );

        return response()->json([
            'data' => [
                'target_price' => $request->input('target_price'),
                'direction' => $alert->direction->value,
                'status' => $alert->status->value,
            ],
        ], 201);
    }
}
