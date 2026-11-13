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
        try {
            $record = $this->purchaseGoldService->store($request->validated(), (int) $request->user()->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Purchase gold transaction recorded successfully.',
                'data'    => new PurchaseGoldResource($record),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            // The service's own rules (sources add up, enough cash or bank balance).
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Transaction failed. All changes have been rolled back.',
            ], 500);
        }
    }
}
