<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockOutRequest;
use App\Http\Requests\StockInRequest;
use App\Http\Requests\ItemChangeRequest;
use App\Http\Requests\ItemConversionRequest;
use App\Http\Requests\GmsOutRequest;
use App\Http\Requests\GmsInRequest;
use App\Http\Requests\NumericWasteRequest;
use App\Http\Requests\NumericWastageInRequest;
use App\Http\Requests\HideStockRequest;
use App\Http\Requests\CashOutRequest;
use App\Services\StockOutService;
use App\Services\StockInService;
use App\Services\AutoEntryService;
use App\Services\ReportService;
use App\Http\Requests\AutoEntryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\StockDetails;

class StockDetailsController extends Controller
{
    /**
     * ============================================================
     * GET STOCK HISTORY (For Cash Dashboard Bottom Table)
     * ============================================================
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $headId = $request->query('head_id');
            $cashUserId = $request->query('cash_user_id');
            $remarks = $request->query('remarks');
            $perPage = $request->query('per_page', 50);

            $query = StockDetails::with(['item', 'givenByUser', 'givenToUser', 'addedByUser'])
                ->where('is_completed', 0)
                ->where('is_freezed', 0);

            if ($remarks && $cashUserId && $headId) {
                // Specific filter for the Cash Dashboard (e.g. PURCHASE_GOLD)
                $query->where('given_to', $headId)
                      ->where('given_by', $cashUserId)
                      ->where('remarks', $remarks);
            }

            $stockDetails = $query->orderBy('stock_id', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Stock history retrieved successfully',
                'data' => $stockDetails
            ], 200);

        } catch (\Exception $e) {
            Log::error('getHistory failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected StockOutService $stockOutService;
    protected StockInService $stockInService;
    protected AutoEntryService $autoEntryService;
    protected ReportService $reportService;

    public function __construct(
        StockOutService $stockOutService,
        StockInService $stockInService,
        AutoEntryService $autoEntryService,
        ReportService $reportService
    ) {
        $this->stockOutService = $stockOutService;
        $this->stockInService = $stockInService;
        $this->autoEntryService = $autoEntryService;
        $this->reportService = $reportService;
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
            $result = $this->stockOutService->createStockOut($request->validated(), $addedBy);

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
            $history = $this->stockOutService->createItemChange($request->validated(), $addedBy);

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
            $conversions = $this->stockOutService->createItemConversion($request->validated(), $addedBy);

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
            $gmsHistory = $this->stockOutService->createGmsOut($request->validated(), $addedBy);

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
            $nw = $this->stockOutService->createNumericWaste($request->validated(), $addedBy);

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
            $this->stockOutService->hideStocks($request->input('stock_ids'));

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
            $cashTxn = $this->stockOutService->createCashOut($request->validated(), $addedBy);

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

    /**
     * 7. Register a normal IN stock transaction (New In)
     */
    public function postStockIn(StockInRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $result = $this->stockInService->createStockIn($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Stock In Created Successfully',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postStockIn failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Stock In transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 8. Register a GMS IN stock transaction (GMS In)
     */
    public function postGmsIn(GmsInRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $result = $this->stockInService->createGmsIn($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'GMS In Created Successfully',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postGmsIn failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process GMS In transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 9. Register a Numeric Wastage IN stock transaction (Numeric Wastage In)
     */
    public function postNumericWasteIn(NumericWastageInRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $result = $this->stockInService->createNumericWasteIn($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Numeric Wastage In Created Successfully',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postNumericWasteIn failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Numeric Wastage In transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 10. Register an Auto Entry stock transfer transaction
     */
    public function postAutoEntry(AutoEntryRequest $request): JsonResponse
    {
        try {
            $addedBy = $this->getActingUserId($request);
            $result = $this->autoEntryService->executeAutoTransfer($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Auto Entry transaction processed successfully.',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postAutoEntry failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Auto Entry transaction.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 11. Retrieve Items OB & CB running ledger report
     */
    public function getHistoryItemsObcb(Request $request): JsonResponse
    {
        try {
            $headId = $this->getActingUserId($request);
            $result = $this->reportService->getItemsObcbReport($request->all(), $headId);

            return response()->json([
                'success' => true,
                'message' => 'Items OB & CB report compiled successfully.',
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::getHistoryItemsObcb failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to compile Items OB & CB report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
