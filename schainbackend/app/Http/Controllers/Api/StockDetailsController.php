<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockOutRequest;
use App\Http\Requests\ItemChangeRequest;
use App\Http\Requests\ItemConversionRequest;
use App\Http\Requests\GmsOutRequest;
use App\Http\Requests\NumericWasteRequest;
use App\Http\Requests\HideStockRequest;
use App\Http\Requests\CashOutRequest;
use App\Services\StockInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StockDetailsController extends Controller
{
    protected StockInventoryService $inventoryService;

    public function __construct(StockInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Helper to resolve the acting user's ID.
     */
    protected function getActingUserId(Request $request): int
    {
        return $request->user()->user_id ?? (int)$request->header('X-User-ID', 8);
    }

    /**
     * 1. Register a normal OUT stock transaction (New Out)
     */
    public function postStockOut(StockOutRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $result = $this->inventoryService->createStockOut($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Stock Out Created Successfully',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postStockOut failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Stock Out transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 2. Swaps stock weight from one item category to another (Item Change)
     */
    public function postItemChange(ItemChangeRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $history = $this->inventoryService->createItemChange($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Item Changed Successfully',
                'data' => $history,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postItemChange failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Item Change transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 3. Item conversion submodule (Item Conversion)
     */
    public function postItemConversion(ItemConversionRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $conversions = $this->inventoryService->createItemConversion($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Item Converted Successfully',
                'data' => $conversions,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postItemConversion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Item Conversion transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 4. Send raw gold to a goldsmith (GMS Out)
     */
    public function postGmsOut(GmsOutRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $gmsHistory = $this->inventoryService->createGmsOut($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'GMS Out Created Successfully',
                'data' => $gmsHistory,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postGmsOut failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process GMS Out transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 4. Numeric wastage out
     */
    public function postNumericWaste(NumericWasteRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $nw = $this->inventoryService->createNumericWaste($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Numeric Waste Created Successfully',
                'data' => $nw,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postNumericWaste failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Numeric Wastage transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 5. Hide specific stock details and their parent
     */
    public function postHide(HideStockRequest $request): JsonResponse
    {
        try {
            $this->inventoryService->hideStocks($request->input('stock_ids'));

            return response()->json([
                'success' => true,
                'message' => 'Stock Hidden Successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postHide failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Hide transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 6. Cash / RTGS Transfer (Cash Out)
     */
    public function postCash(CashOutRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $cashTxn = $this->inventoryService->createCashOut($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Cash Out Created Successfully',
                'data' => $cashTxn,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postCash failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Cash transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
