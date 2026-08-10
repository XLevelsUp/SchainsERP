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
        $addedBy = auth()->id() ?? 1;

        $record = $this->saleGoldService->store($request->validated(), $addedBy);

        return response()->json([
            'success' => true,
            'message' => 'Sale gold transaction recorded successfully.',
            'data'    => new SaleGoldResource($record),
        ], 201);
    }
}