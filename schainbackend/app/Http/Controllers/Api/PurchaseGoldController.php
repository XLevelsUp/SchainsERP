<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseGoldRequest;
use App\Http\Resources\PurchaseGoldResource;
use App\Services\PurchaseGoldService;
use Illuminate\Http\JsonResponse;

class PurchaseGoldController extends Controller
{
    protected PurchaseGoldService $purchaseGoldService;

    public function __construct(PurchaseGoldService $purchaseGoldService)
    {
        $this->purchaseGoldService = $purchaseGoldService;
    }

    /**
     * Store a Purchase Gold transaction.
     */
    public function store(StorePurchaseGoldRequest $request): JsonResponse
    {
        $addedBy = auth()->id() ?? 1;

        $record = $this->purchaseGoldService->store($request->validated(), $addedBy);

        return response()->json([
            'success' => true,
            'message' => 'Purchase gold transaction recorded successfully.',
            'data'    => new PurchaseGoldResource($record),
        ], 201);
    }
}
