<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashTransactionReportResource;
use App\Models\CashTxnDetail;
use App\Models\CashCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Get Cash Transactions for the Reports Page.
     * Legacy UI Replication.
     */
    public function getCashTransactionsObcb(Request $request): JsonResponse
    {
        try {
            $query = CashTxnDetail::with([
                'givenByUser:user_id,name,user_name,category_name',
                'givenToUser:user_id,name,user_name,category_name',
                'category:category_id,category_name',
                'bank:bank_id,account_name',
                'cashToGold' // Needed for cash-to-gold specific logic in resource
            ]);

            // Single category filter
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->query('category_id'));
            }

            // Main category array filter (fetching all sub categories)
            if ($request->filled('cash_main_category_id')) {
                $mainCategoryIds = $request->query('cash_main_category_id');
                if (is_string($mainCategoryIds)) {
                    $mainCategoryIds = explode(',', $mainCategoryIds);
                }
                
                $categoryIds = CashCategory::whereIn('cash_main_category_id', (array)$mainCategoryIds)
                    ->pluck('category_id')
                    ->toArray();
                    
                $query->whereIn('category_id', $categoryIds);
            }

            // Transaction Type filter
            if ($request->filled('type')) {
                $type = $request->query('type');
                if (is_string($type) && strpos($type, ',') !== false) {
                    $type = explode(',', $type);
                }
                if (is_array($type)) {
                    $query->whereIn('type', $type);
                } else {
                    $query->where('type', $type);
                }
            }

            // Bank ID array filter
            if ($request->filled('bank_id')) {
                $bankIds = $request->query('bank_id');
                if (is_string($bankIds)) {
                    $bankIds = explode(',', $bankIds);
                }
                
                // Dynamic amount logic: check bank_account_id OR related amount sources
                $query->where(function ($q) use ($bankIds) {
                    $q->whereIn('cash_txn_details.bank_account_id', (array)$bankIds)
                      ->orWhereHas('amountSources', function($subQuery) use ($bankIds) {
                          $subQuery->whereIn('bank_id', (array)$bankIds);
                      });
                });
            }

            // Standard From/To Date (added_at in legacy -> created_at in new)
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', date('Y-m-d', strtotime($request->query('from_date'))));
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', date('Y-m-d', strtotime($request->query('to_date'))));
            }

            // Bank Entry Dates
            if ($request->filled('bank_entry_from_date')) {
                $query->whereDate('bank_entry_date', '>=', date('Y-m-d', strtotime($request->query('bank_entry_from_date'))));
            }
            if ($request->filled('bank_entry_to_date')) {
                $query->whereDate('bank_entry_date', '<=', date('Y-m-d', strtotime($request->query('bank_entry_to_date'))));
            }

            // Always order descending by ID
            $query->orderBy('txn_id', 'desc');

            // Handle Pagination / "Show All"
            $isAll = $request->query('is_all', 0);
            $perPage = $request->query('page_size', 50);

            if ($isAll == 1 || $isAll === 'true') {
                // Safety limit: 5000 max to prevent memory crashes
                $transactions = $query->limit(5000)->get();
                $totalCount = $transactions->count(); 
            } else {
                $paginator = $query->paginate($perPage);
                $transactions = $paginator->getCollection();
                $totalCount = $paginator->total();
            }

            return response()->json([
                'success' => true,
                'message' => 'Cash Transactions Report retrieved successfully',
                'parameters' => [
                    'count' => $totalCount,
                    'content' => CashTransactionReportResource::collection($transactions),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('ReportController@getCashTransactions failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Cash Transactions Report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
