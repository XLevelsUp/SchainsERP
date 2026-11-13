<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleGoldRequest;
use App\Http\Resources\SaleGoldResource;
use App\Services\SaleGoldService;
use Illuminate\Http\JsonResponse;

class SaleGoldController extends Controller
{
    protected SaleGoldService $saleGoldService;

    public function __construct(SaleGoldService $saleGoldService)
    {
        $this->saleGoldService = $saleGoldService;
    }

    /**
     * Store a Sale Gold transaction.
     */
    public function store(StoreSaleGoldRequest $request): JsonResponse
    {
        try {
            $record = $this->saleGoldService->store($request->validated(), (int) $request->user()->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Sale gold transaction recorded successfully.',
                'data'    => new SaleGoldResource($record),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            // The service's own rules (payment sources must add up to the total).
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