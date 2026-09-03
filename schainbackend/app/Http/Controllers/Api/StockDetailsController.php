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
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\StockCashHistoryResource;
use App\Models\StockDetails;
use App\Models\Item;
use App\Http\Resources\AvailableMetalResource;

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
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
            $filters = $request->all();
            
            $result = $this->reportService->getStockHistory($filters, $headId);

            return response()->json([
                'success' => true,
                'message' => 'Stock history retrieved successfully',
                'data' => $result
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
        return $request->user()->user_id ?? (int)$request->header('X-User-ID', 1);
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
     * Retrieve ID-Wise deep stock report and lineage
     */
    public function getIdWiseReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'stock_id' => 'required|integer'
            ]);

            $headId = $this->getActingUserId($request);
            $result = $this->reportService->getIdWiseStockReport($request->input('stock_id'), $headId);

            return response()->json([
                'success' => true,
                'message' => 'ID-Wise stock report compiled successfully.',
                'data' => $result,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stock ID not found.',
                'errors' => 'The provided Stock ID does not exist.'
            ], 404);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::getIdWiseReport failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to compile ID-Wise stock report.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An unexpected error occurred.'
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

    public function getConsolidatedReport(Request $request): JsonResponse
    {
        try {
            $headId = $this->getActingUserId($request);
            $result = $this->reportService->getConsolidatedReport($request->all(), $headId);

            return response()->json([
                'success' => true,
                'message' => 'Consolidated report retrieved successfully.',
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::getConsolidatedReport failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to compile consolidated report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCashTransactionHistory(Request $request)
    {
        try {
            $headId = $request->query('head_id');
            $cashUserId = $request->query('cash_user_id');
            $perPage = $request->query('per_page', 50);

            $page = $request->query('page', 1);

            $cacheTag = "cash_history_{$headId}_{$cashUserId}";
            $cacheKey = "stock_cash_history_{$headId}_{$cashUserId}_page_{$page}_perPage_{$perPage}";

            $rememberClosure = function () use ($headId, $cashUserId, $perPage) {
                $query = StockDetails::with([
                    'item:item_id,item_name', 
                    'givenBy:user_id,name', 
                    'givenTo:user_id,name'
                ])
                    ->where('is_completed', 0)
                    ->where('is_freezed', 0)
                    ->whereIn('remarks', ['PURCHASE_GOLD', 'SALE_GOLD', 'GOLD_TO_CASH', 'CASH_TO_GOLD']);

                if ($cashUserId && $headId) {
                    // Get transactions where these two users are involved (either direction)
                    $query->where(function ($q) use ($headId, $cashUserId) {
                        $q->where(function ($q1) use ($headId, $cashUserId) {
                            $q1->where('given_to', $headId)->where('given_by', $cashUserId);
                        })->orWhere(function ($q2) use ($headId, $cashUserId) {
                            $q2->where('given_by', $headId)->where('given_to', $cashUserId);
                        });
                    });
                }

                $stockDetails = $query->orderBy('stock_id', 'desc')->paginate($perPage);
                return StockCashHistoryResource::collection($stockDetails)->response()->getData(true);
            };

            if (\Illuminate\Support\Facades\Cache::supportsTags()) {
                $stockDetailsData = Cache::tags([$cacheTag])->remember($cacheKey, 86400, $rememberClosure);
            } else {
                $stockDetailsData = $rememberClosure();
            }

            return response()->json([
                'success' => true,
                'message' => 'Cash stock history retrieved successfully',
                'data' => $stockDetailsData
            ], 200);

        } catch (\Exception $e) {
            Log::error('getCashTransactionHistory failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cash stock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available metal stocks for the frontend pop-up selection.
     */
    public function getAvailableMetals(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('user_id');
            $itemId = $request->query('item_id');

            if (!$userId || !$itemId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id and item_id are required'
                ], 400);
            }

            // Validate that the requested item is actually a "metal"
            $item = Item::find($itemId);
            if (!$item || strtolower($item->item_name) !== 'metal') {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected item is not valid for metal selection'
                ], 400);
            }

            // Query available metal stocks replicating legacy logic exactly
            $metals = StockDetails::with([
                    'givenBy:user_id,name,user_name',
                    'givenTo:user_id,name,user_name'
                ])
                ->where('given_to', $userId)
                ->where('balance', '>', 0)
                ->where('remarks', '!=', 'CASH_TO_GOLD')
                ->whereIn('entry_type', ['NORMAL', 'EMPTOHEAD', 'HEADTOHEAD'])
                ->where(function ($q) use ($itemId) {
                    $q->where(function ($q1) use ($itemId) {
                        $q1->where('entry_type', 'NORMAL')->where('item_id', $itemId);
                    })->orWhere(function ($q2) use ($itemId) {
                        $q2->where('entry_type', '!=', 'NORMAL')->where('to_item_id', $itemId);
                    });
                })
                ->where(function ($q) {
                    $q->where(function ($q1) {
                        $q1->whereIn('type', ['ITEMCHANGE', 'ITEMCONVERSION'])->where('stock_type', '!=', 'OUT');
                    })->orWhere(function ($q2) {
                        $q2->whereNotIn('type', ['ITEMCHANGE', 'ITEMCONVERSION'])->whereIn('stock_type', ['IN', 'OUT']);
                    });
                })
                ->where('is_hided', 0)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Available metal stocks retrieved successfully',
                'data' => AvailableMetalResource::collection($metals)
            ], 200);

        } catch (\Exception $e) {
            Log::error('getAvailableMetals failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metal stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET HEAD STOCKS
     */
    public function getHeadStocks(Request $request): JsonResponse
    {
        try {
            $headId = $request->query('user_id') ?: $this->getActingUserId($request);
            
            $filters = $request->only([
                'head_txn_from_date',
                'head_txn_from_time',
            ]);

            $result = $this->reportService->getHeadStocks($filters, (int) $headId);

            return response()->json([
                'success' => true,
                'message' => 'Head stocks retrieved successfully',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('getHeadStocks failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve head stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
