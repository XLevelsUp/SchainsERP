<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FitemRequest;
use App\Services\FitemService;
use Illuminate\Http\JsonResponse;

class FitemController extends Controller
{
    protected FitemService $fitemService;

    public function __construct(FitemService $fitemService)
    {
        $this->fitemService = $fitemService;
    }

    public function store(FitemRequest $request): JsonResponse
    {
        try {
            $actingUserId = (int) $request->user()->user_id;
            
            $result = $this->fitemService->processFitems($request->validated(), $actingUserId);

            return response()->json([
                'success' => true,
                'message' => 'F-Items processed successfully.',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process F-Items',
            ], 500);
        }
    }
}
