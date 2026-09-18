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

    public function exportHistoryItemsObcb(Request $request)
    {
        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
            $filters = $request->all();
            $filters['is_export'] = true;
            $result = $this->reportService->getItemsObcbReport($filters, $headId);

            $records = $result['details']['records'] ?? [];

            return response()->streamDownload(function () use ($records) {
                $file = fopen('php://output', 'w');
                if (count($records) > 0) {
                    fputcsv($file, array_keys((array)$records[0]));
                    foreach ($records as $row) {
                        fputcsv($file, (array)$row);
                    }
                }
                fclose($file);
            }, 'history_items_obcb.csv');

        } catch (\Throwable $e) {
            Log::error('StockDetailsController::exportHistoryItemsObcb failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Export failed.'], 500);
        }
    }

    public function exportConsolidatedReport(Request $request)
    {
        try {
            $headId = $request->query('head_id') ?? $this->getActingUserId($request);
            $filters = $request->all();
            $filters['is_export'] = true;
            $result = $this->reportService->getConsolidatedReport($filters, $headId);

            $outRecords = $result['out_details']['records'] ?? [];
            $inRecords = $result['in_details']['records'] ?? [];

            return response()->streamDownload(function () use ($outRecords, $inRecords) {
                $file = fopen('php://output', 'w');
                
                if (count($outRecords) > 0) {
                    fputcsv($file, ['TYPE']);
                    fputcsv($file, ['OUT_RECORDS']);
                    fputcsv($file, array_keys((array)$outRecords[0]));
                    foreach ($outRecords as $row) {
                        fputcsv($file, (array)$row);
                    }
                }

                if (count($inRecords) > 0) {
                    fputcsv($file, []);
                    fputcsv($file, ['TYPE']);
                    fputcsv($file, ['IN_RECORDS']);
                    fputcsv($file, array_keys((array)$inRecords[0]));
                    foreach ($inRecords as $row) {
                        fputcsv($file, (array)$row);
                    }
                }
                fclose($file);
            }, 'consolidated_report.csv');

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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getConsolidatedReport(Request $request): JsonResponse
    {
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
                    ->whereIn('remarks', ['PURCHASE_GOLD', 'SALE_GOLD', 'GOLD_TO_CASH', 'CASH_TO_GOLD', 'IN_CASH_CONVERTER', 'OUT_CASH_CONVERTER']);

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
                ->whereNull('stock_in_id'); // Only parent IN lots

            if ($date && $time) {
                $dateTime = date('Y-m-d H:i:s', strtotime("$date $time"));
                $query->selectRaw(
                    "stock_details.*, COALESCE((SELECT SUM(s.grams) FROM stock_details s WHERE s.stock_in_id = stock_details.stock_id AND s.added_at <= ?), 0) as used_grams",
                    [$dateTime]
                )
                ->havingRaw('grams - used_grams > 0')
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
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}


