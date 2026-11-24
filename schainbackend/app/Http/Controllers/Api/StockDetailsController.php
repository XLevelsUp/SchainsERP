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
use App\Http\Controllers\Concerns\ScopesRecordAccess;

class StockDetailsController extends Controller
{
    use ScopesRecordAccess;

    public function exportHistoryItemsObcb(Request $request)
    {
        if ($denied = $this->forbidOtherUsersBalanceReport($request, ['head_id', 'employee_id'])) {
            return $denied;
        }

        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
            $filters = $request->all();
            $filters['is_export'] = true;
            $result = $this->reportService->getItemsObcbReport($filters, $headId);

            return \App\Support\ReportCsv::itemsObcb($result);

        } catch (\Throwable $e) {
            Log::error('StockDetailsController::exportHistoryItemsObcb failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed.'], 500);
        }
    }

    public function exportConsolidatedReport(Request $request)
    {
        if ($denied = $this->forbidOtherUsersBalanceReport($request, ['head_id', 'head_user_id', 'employee_id', 'user_id', 'retailer_id'])) {
            return $denied;
        }

        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
            $filters = $request->all();
            $filters['is_export'] = true;
            $result = $this->reportService->getConsolidatedReport($filters, $headId);

            return \App\Support\ReportCsv::consolidated($result);

        } catch (\Throwable $e) {
            Log::error('StockDetailsController::exportConsolidatedReport failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed.'], 500);
        }
    }
    /**
     * ============================================================
     * GET STOCK HISTORY (For Cash Dashboard Bottom Table)
     * ============================================================
     */
    public function getHistory(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['head_id', 'employee_id'])) {
            return $denied;
        }

        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
            $filters = $request->all();
            
            // Rows are labelled In/Out from the logged-in user's side.
            $result = $this->reportService->getStockHistory($filters, $headId, $this->getActingUserId($request));

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
        // Always the token's user. The old `X-User-ID` header fallback let a
        // caller act as anyone (BACKEND_TODO §7).
        return $this->actingUserId($request);
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

            // Item Change is only ever on your own stock (2026-11-13).
            if ($request->filled('user_id') && (int) $request->input('user_id') !== $addedBy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item Change is for your own stock only.',
                    'errors' => ['user_id' => ['Item Change is for your own stock only.']],
                ], 422);
            }

            $history = $this->stockOutService->createItemChange($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Item Changed Successfully',
                'data' => $history,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            // Our own checks: the lot isn't the user's, or is another item.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postItemChange failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Item Change transaction.',
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

            // Item Conversion is only ever on your own stock (2026-11-13).
            if ($request->filled('user_id') && (int) $request->input('user_id') !== $addedBy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item Conversion is for your own stock only.',
                    'errors' => ['user_id' => ['Item Conversion is for your own stock only.']],
                ], 422);
            }

            $conversions = $this->stockOutService->createItemConversion($request->validated(), $addedBy);

            return response()->json([
                'success' => true,
                'message' => 'Item Converted Successfully',
                'data' => $conversions,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            // Our own checks: the lot isn't the user's, or is another item.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postItemConversion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Item Conversion transaction.',
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
            ], 500);
        }
    }

    /**
     * Unhide specific stock details and their parent
     */
    public function postUnhide(HideStockRequest $request): JsonResponse
    {
        try {
            $this->stockOutService->unhideStocks($request->input('stock_ids'));

            return response()->json([
                'success' => true,
                'message' => 'Stock Unhidden Successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('StockDetailsController::postUnhide failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Unhide transaction.',
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

            if ($denied = $this->forbidOtherUsersRecords($request, ['head_id'])) {
                return $denied;
            }

            // A non-head may only open a stock row they are a party to.
            $stock = StockDetails::find($request->input('stock_id'));
            if ($stock && ($denied = $this->forbidUnlessParty($request, $stock->given_by, $stock->given_to))) {
                return $denied;
            }

            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
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
            ], 500);
        }
    }

    /**
     * 11. Retrieve Items OB & CB running ledger report
     */
    public function getHistoryItemsObcb(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersBalanceReport($request, ['head_id', 'employee_id'])) {
            return $denied;
        }

        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
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
            ], 500);
        }
    }

    public function getConsolidatedReport(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersBalanceReport($request, ['head_id', 'head_user_id', 'employee_id', 'user_id', 'retailer_id'])) {
            return $denied;
        }

        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
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
            ], 500);
        }
    }

    public function getCashTransactionHistory(Request $request)
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['head_id'])) {
            return $denied;
        }

        try {
            // Without head_id this used to return every user's rows.
            $headId = $request->query('head_id') ?: $this->actingUserId($request);
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
                    ->whereIn('remarks', ['PURCHASE_GOLD', 'SALE_GOLD', 'GOLD_TO_CASH', 'CASH_TO_GOLD', 'IN_CASH_CONVERTER', 'OUT_CASH_CONVERTER']);

                if ($headId) {
                    $query->where(function ($q) use ($headId, $cashUserId) {
                        if ($cashUserId) {
                            // Get transactions where these two users are involved (either direction)
                            $q->where(function ($q1) use ($headId, $cashUserId) {
                                $q1->where('given_to', $headId)->where('given_by', $cashUserId);
                            })->orWhere(function ($q2) use ($headId, $cashUserId) {
                                $q2->where('given_by', $headId)->where('given_to', $cashUserId);
                            });
                        } else {
                            // Only head_id provided, return all their transactions
                            $q->where('given_to', $headId)->orWhere('given_by', $headId);
                        }
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
            ], 500);
        }
    }

    /**
     * Get available metal stocks for the frontend pop-up selection.
     */
    public function getAvailableMetals(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['user_id'])) {
            return $denied;
        }

        try {
            $userId = $request->query('user_id');
            $itemId = $request->query('item_id');

            if (!$userId || !$itemId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id and item_id are required'
                ], 400);
            }

            // Validate that the requested item is mapped as a metal popup item
            $item = Item::find($itemId);
            $metalItemIds = \App\Models\SystemSetting::get('metal_popup_items', []);
            if (!$item || !in_array($item->item_id, $metalItemIds)) {
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
            ], 500);
        }
    }

    /**
     * GET HEAD STOCKS
     */
    public function getHeadStocks(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['user_id'])) {
            return $denied;
        }

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
            ], 500);
        }
    }

    /**
     * Generic available stock lots for any item (not limited to Metal).
     * Used by Item Change and Item Conversion to pick a stock_in_id for any item type.
     *
     * GET /api/v1/stock-details/available-lots
     * Query params:
     *   user_id   (required) - the user whose IN lots to list
     *   item_id   (required) - the item to filter by
     *   date      (optional) - Y-m-d, balances as of this date
     *   time      (optional) - H:i:s, combined with date
     *   page_size (optional) - default 50
     */
    public function getAvailableStockLots(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['user_id'])) {
            return $denied;
        }

        try {
            $userId   = $request->query('user_id');
            $itemId   = $request->query('item_id');
            $date     = $request->query('date');
            $time     = $request->query('time');
            $pageSize = (int) $request->query('page_size', 50);

            if (!$userId || !$itemId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id and item_id are required.'
                ], 422);
            }

            $query = StockDetails::with(['givenBy'])
                ->where('given_to', $userId)
                ->where('item_id', $itemId)
                ->whereIn('stock_type', ['IN'])
                ->where('is_hided', 0)
                ->whereNull('stock_in_id'); // Only parent IN lots

            if ($date && $time) {
                $dateTime = date('Y-m-d H:i:s', strtotime("$date $time"));
                $query->selectRaw(
                    "stock_details.*, COALESCE((SELECT SUM(s.grams) FROM stock_details s WHERE s.stock_in_id = stock_details.stock_id AND s.added_at <= ?), 0) as used_grams",
                    [$dateTime]
                )
                ->whereRaw('grams - COALESCE((SELECT SUM(s.grams) FROM stock_details s WHERE s.stock_in_id = stock_details.stock_id AND s.added_at <= ?), 0) > 0', [$dateTime])
                ->orderBy('stock_id', 'desc');
            } else {
                $query->select('stock_details.*')
                    ->where('balance', '>', 0)
                    ->orderBy('stock_id', 'desc');
            }

            $paginated = $query->paginate($pageSize);

            $records = $paginated->getCollection()->map(function ($lot) use ($date, $time) {
                $balance = ($date && $time)
                    ? round($lot->grams, 3) - round($lot->used_grams ?? 0, 3)
                    : $lot->balance;

                return [
                    'stock_id'      => $lot->stock_id,
                    'item_id'       => $lot->item_id,
                    'balance'       => round($balance, 3),
                    'grams'         => $lot->grams,
                    'touch'         => $lot->touch,
                    'purity'        => $lot->purity,
                    'added_at'      => (string) $lot->added_at,
                    'party_name'    => $lot->givenBy?->name,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Available stock lots retrieved successfully.',
                'data'    => [
                    'current_page'  => $paginated->currentPage(),
                    'data'          => $records,
                    'total'         => $paginated->total(),
                    'per_page'      => $paginated->perPage(),
                    'last_page'     => $paginated->lastPage(),
                    'next_page_url' => $paginated->nextPageUrl(),
                    'prev_page_url' => $paginated->previousPageUrl(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('getAvailableStockLots failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available stock lots.',
            ], 500);
        }
    }
}


