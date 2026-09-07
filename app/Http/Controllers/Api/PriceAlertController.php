<?php

namespace App\Http\Controllers\Api;

use App\Domain\PriceAlert\Actions\CreatePriceAlertAction;
use App\Domain\PriceAlert\Actions\DeletePriceAlertAction;
use App\Domain\PriceAlert\Enums\AlertDirection;
use App\Domain\PriceAlert\Enums\DeletePriceAlertResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePriceAlertRequest;
use App\Models\PriceAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

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

    public function destroy(PriceAlert $alert, DeletePriceAlertAction $action): Response|JsonResponse
    {
        $result = $action->handle(
            userId: Auth::id(),
            alert: $alert,
        );

        return match ($result) {
            DeletePriceAlertResult::DELETED => response()->noContent(),
            DeletePriceAlertResult::NOT_FOUND => response()->json([
                'message' => 'Alert not found.',
            ], 404),
            DeletePriceAlertResult::NOT_DELETABLE => response()->json([
                'message' => 'Only active alerts that have not been triggered can be deleted.',
            ], 400),
        };
    }
}
