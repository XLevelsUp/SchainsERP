<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoldToCashRequest;
use App\Http\Resources\GoldToCashResource;
use App\Services\GoldToCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoldToCashController extends Controller
{
    public function __construct(private readonly GoldToCashService $service) {}

    /**
     * GET /api/v1/gold-to-cash
     * List Gold To Cash transactions with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $records = $this->service->index($request->only([
            'head_id', 'customer_id', 'from_date', 'to_date', 'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data'    => GoldToCashResource::collection($records->items()),
            'meta'    => [
                'current_page' => $records->currentPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/gold-to-cash
     * Save Gold To Cash transaction — writes 5 tables atomically.
     *
     * Supports multiple amount_sources (CASH_ON_HAND + BANK mixed):
     * {
     *   "head_id": 842, "customer_id": 886,
     *   "total_gold": 7.692,
     *   "touch": 100,
     *   "total_grams": 7.692,
     *   "per_gram_cash": 6500,
     *   "total_cash": 50000,
     *   "amount_sources": [
     *     { "source": "CASH_ON_HAND", "bank_id": null, "amount": 30000 },
     *     { "source": "BANK",         "bank_id": 2,    "amount": 20000 }
     *   ]
     * }
     */
    public function store(StoreGoldToCashRequest $request): JsonResponse
    {
        try {
            $record = $this->service->store(
                $request->validated(),
                auth()->id() ?? 1
            );

            return response()->json([
                'success' => true,
                'message' => 'Gold To Cash transaction saved successfully.',
                'data'    => new GoldToCashResource($record),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction failed. All changes have been rolled back.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/v1/gold-to-cash/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $record = $this->service->find($id);
            return response()->json([
                'success' => true,
                'data'    => new GoldToCashResource($record),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        }
    }

    /**
     * DELETE /api/v1/gold-to-cash/{id}
     * Reverses all balance changes atomically.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
            return response()->json([
                'success' => true,
                'message' => 'Gold To Cash record deleted and all balances reversed.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed. All changes have been rolled back.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
