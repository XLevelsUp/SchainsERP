<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashTransactionReportResource;
use App\Models\CashTxnDetail;
use App\Models\CashCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Concerns\ScopesRecordAccess;

class ReportController extends Controller
{
    use ScopesRecordAccess;

    /**
     * Get Cash Transactions for the Reports Page.
     * Legacy UI Replication.
     */
    public function getCashTransactionsObcb(Request $request): JsonResponse
    {
        try {
            $query = CashTxnDetail::with([
                'givenByUser:user_id,name,user_name,role_id',
                'givenByUser.role:id,role',
                'givenToUser:user_id,name,user_name,role_id',
                'givenToUser.role:id,role',
                'category:category_id,category_name',
                'bank:bank_id,account_name',
                'cashToGold' // Needed for cash-to-gold specific logic in resource
            ]);

            // Heads and users who can act as head see every entry (balances are hidden
            // from the latter by the resource); anyone else only entries they are a party to.
            if (!$this->actingUserCanActAsHead($request)) {
                $self = $this->actingUserId($request);
                $query->where(function ($q) use ($self) {
                    $q->where('sender_id', $self)->orWhere('recipient_id', $self);
                });
            }

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
            ], 500);
        }
    }
    protected $reportService;

    public function __construct(\App\Services\ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get Live Metal Balance for a User (or Admin targeting a User)
     */
    public function getLiveMetalBalance(Request $request): JsonResponse
    {
        try {
            // Determine the target user_id
            $actingUserId = $this->actingUserId($request);
            $targetUserId = $actingUserId;

            // Admin Override Check
            // In Laravel, checking if user is admin usually involves role_id == 1 or checking role->role
            // Wait, we need to fetch the acting user to see if they are admin.
            $actingUser = \App\Models\UserDetail::find($actingUserId);
            
            // Assuming role->role == 'HEAD'
            if ($actingUser && in_array(strtoupper(optional($actingUser->role)->role), ['HEAD'])) {
                if ($request->filled('view_as')) {
                    $targetUserId = (int) $request->query('view_as');
                } elseif ($request->filled('user_id')) {
                    $targetUserId = (int) $request->query('user_id');
                }
            }

            $params = $request->all();
            
            $result = $this->reportService->getLiveMetalBalanceReport($params, $targetUserId);

            return response()->json([
                'success' => true,
                'message' => 'Metal live report fetched successfully.',
                'data' => $result
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('ReportController@getLiveMetalBalance failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Metal Live Report',
            ], 500);
        }
    }

    /**
     * Get One Day Action Report (Stock & Cash)
     */
    public function getOneDayActionReport(Request $request, \App\Services\ReportService $reportService): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['employee_id'])) {
            return $denied;
        }

        if ($invalid = \App\Support\OneDayActionFilters::invalid($request)) {
            return $invalid;
        }

        try {
            // The token's user. This used to read an X-User-ID header and fall
            // back to user 1 without it (BACKEND_TODO §7).
            $headId = $this->actingUserId($request);

            // Stock and cash sections, defaulting to today.
            $result = $reportService->getOneDayActionReport(\App\Support\OneDayActionFilters::from($request), $headId);

            return response()->json([
                'success' => true,
                'message' => 'One day action report fetched successfully.',
                'data' => $result
            ], 200);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ReportController::getOneDayActionReport failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch one day action report.',
            ], 500);
        }
    }
    public function getDaywiseGrandReport(Request $request): JsonResponse
    {
        if ($denied = $this->forbidOtherUsersRecords($request, ['head_user_id', 'employee_id', 'user_id', 'customer_id', 'retailer_id'])) {
            return $denied;
        }

        try {
            // The token's user. This used to read an X-User-ID header and fall
            // back to user 1 without it (BACKEND_TODO §7).
            $headId = $this->actingUserId($request);
            $filters = $request->all();

            $result = $this->reportService->getDaywiseGrandReport($filters, $headId);

            return response()->json([
                'success' => true,
                'message' => 'Daywise Grand Report fetched successfully.',
                'data' => $result
            ], 200);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ReportController::getDaywiseGrandReport failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch daywise grand report.',
            ], 500);
        }
    }
}
