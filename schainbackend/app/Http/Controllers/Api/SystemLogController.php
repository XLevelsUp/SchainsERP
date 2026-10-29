<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemLogResource;
use App\Models\SystemLog;
use App\Models\UserDetail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemLogController extends Controller
{
    /**
     * Display a listing of the system logs with rich filtering and pagination.
     * Restricted to HEAD role only.
     */
    public function index(Request $request): JsonResponse
    {
        // Permission check: only HEAD role users may read audit logs
        $authUser = auth()->user();
        if (!$authUser || optional($authUser->role)->role !== 'HEAD') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Only HEAD administrators can access system logs.',
            ], 403);
        }

        try {
            $query = SystemLog::with(['user:user_id,name,user_name,role_id']);

            // 1. Module filter (supports single value, array, or comma-separated string, case-insensitive)
            if ($request->filled('module')) {
                $modules = is_array($request->module) 
                    ? $request->module 
                    : array_filter(array_map('trim', explode(',', $request->module)));
                if (!empty($modules)) {
                    $modules = array_map('strtoupper', $modules);
                    $query->whereIn('module', $modules);
                }
            }

            // 2. Action / Sub-Category filter (supports single value, array, or comma-separated string, case-insensitive)
            $actionInput = $request->input('action') ?? $request->input('sub_category');
            if (!empty($actionInput)) {
                $actions = is_array($actionInput) 
                    ? $actionInput 
                    : array_filter(array_map('trim', explode(',', $actionInput)));
                if (!empty($actions)) {
                    $actions = array_map('strtoupper', $actions);
                    $query->whereIn('action', $actions);
                }
            }

            // 3. Exact Date Filter (YYYY-MM-DD)
            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->date);
            }

            // 4. Date Range Filters (from_date / start_date, to_date / end_date)
            $startDate = $request->input('from_date') ?? $request->input('start_date');
            if (!empty($startDate)) {
                $startDateFormatted = strlen($startDate) === 10 ? $startDate . ' 00:00:00' : $startDate;
                $query->where('created_at', '>=', $startDateFormatted);
            }

            $endDate = $request->input('to_date') ?? $request->input('end_date');
            if (!empty($endDate)) {
                $endDateFormatted = strlen($endDate) === 10 ? $endDate . ' 23:59:59' : $endDate;
                $query->where('created_at', '<=', $endDateFormatted);
            }

            // 5. User ID Filter (supports single value, array, or comma-separated)
            if ($request->filled('user_id')) {
                $userIds = is_array($request->user_id) 
                    ? $request->user_id 
                    : array_filter(array_map('trim', explode(',', $request->user_id)));
                if (!empty($userIds)) {
                    $query->whereIn('user_id', $userIds);
                }
            }

            // 6. Customer ID Filter
            if ($request->filled('customer_id')) {
                $customerId = (string) $request->input('customer_id');
                $query->where(function ($q) use ($customerId) {
                    $q->whereRaw("metadata->>'customer_id' = ?", [$customerId])
                      ->orWhereRaw("payload->>'customer_id' = ?", [$customerId])
                      ->orWhereRaw("payload->>'recipient_id' = ?", [$customerId])
                      ->orWhereRaw("payload->>'given_to' = ?", [$customerId]);
                });
            }

            // 7. Customer Name Filter
            if ($request->filled('customer_name')) {
                $customerName = trim($request->input('customer_name'));
                $query->where(function ($q) use ($customerName) {
                    $q->whereRaw("metadata->>'customer_name' ILIKE ?", ["%{$customerName}%"])
                      ->orWhereRaw("metadata->>'recipient_name' ILIKE ?", ["%{$customerName}%"])
                      ->orWhereRaw("metadata->>'given_to_name' ILIKE ?", ["%{$customerName}%"])
                      ->orWhereRaw("payload->>'customer_name' ILIKE ?", ["%{$customerName}%"])
                      ->orWhere('description', 'ILIKE', "%{$customerName}%");
                });
            }

            // 8. Reference Table Filter
            if ($request->filled('reference_table')) {
                $query->where('reference_table', $request->reference_table);
            }

            // 9. Reference ID Filter
            if ($request->filled('reference_id')) {
                $query->where('reference_id', $request->reference_id);
            }

            // 8. Global Search Query
            $search = $request->input('search') ?? $request->input('q');
            if (!empty($search)) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'ILIKE', "%{$search}%")
                      ->orWhere('action', 'ILIKE', "%{$search}%")
                      ->orWhere('module', 'ILIKE', "%{$search}%")
                      ->orWhere('reference_table', 'ILIKE', "%{$search}%")
                      ->orWhereRaw('CAST(reference_id AS TEXT) ILIKE ?', ["%{$search}%"])
                      ->orWhere('ip_address', 'ILIKE', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'ILIKE', "%{$search}%")
                             ->orWhere('user_name', 'ILIKE', "%{$search}%");
                      });
                });
            }

            // 9. Sorting
            $allowedSorts = ['id', 'module', 'action', 'user_id', 'created_at', 'reference_id'];
            $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
            $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortOrder);
            if ($sortBy !== 'id') {
                $query->orderBy('id', 'desc'); // secondary deterministic sort
            }

            // 10. Pagination
            $perPage = (int) ($request->input('per_page') ?? $request->input('limit') ?? 25);
            $perPage = max(1, min($perPage, 250)); // Clamp between 1 and 250

            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'System logs retrieved successfully.',
                'data' => SystemLogResource::collection($logs->items()),
                'pagination' => [
                    'total'        => $logs->total(),
                    'per_page'     => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page'    => $logs->lastPage(),
                    'from'         => $logs->firstItem(),
                    'to'           => $logs->lastItem(),
                ],
                'applied_filters' => [
                    'module'       => $request->input('module'),
                    'action'       => $actionInput,
                    'date'         => $request->input('date'),
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                    'user_id'      => $request->input('user_id'),
                    'reference_id' => $request->input('reference_id'),
                    'search'       => $search,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system logs.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified system log.
     * Restricted to HEAD role only.
     */
    public function show($id): JsonResponse
    {
        // Permission check: only HEAD role users may read audit logs
        $authUser = auth()->user();
        if (!$authUser || optional($authUser->role)->role !== 'HEAD') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Only HEAD administrators can access system logs.',
            ], 403);
        }

        try {
            $log = SystemLog::with(['user:user_id,name,user_name,role_id'])->find($id);

            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => "System log #{$id} not found.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'System log retrieved successfully.',
                'data'    => new SystemLogResource($log),
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system log detail.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return filter metadata options for the admin panel dropdowns.
     * Restricted to HEAD role only.
     */
    public function filters(): JsonResponse
    {
        // Permission check: only HEAD role users may read audit logs
        $authUser = auth()->user();
        if (!$authUser || optional($authUser->role)->role !== 'HEAD') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Only HEAD administrators can access system logs.',
            ], 403);
        }

        try {
            // 1. Available unique modules
            $modules = SystemLog::query()
                ->select('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module');

            // 2. Available actions grouped by module
            $actionsByModule = [];
            $actionsRaw = SystemLog::query()
                ->select('module', 'action')
                ->distinct()
                ->orderBy('module')
                ->orderBy('action')
                ->get();

            foreach ($actionsRaw as $row) {
                $actionsByModule[$row->module][] = $row->action;
            }

            // 3. Flat unique actions list
            $allActions = SystemLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action');

            // 4. Distinct reference tables
            $referenceTables = SystemLog::query()
                ->whereNotNull('reference_table')
                ->select('reference_table')
                ->distinct()
                ->orderBy('reference_table')
                ->pluck('reference_table');

            // 5. Customers for dropdown (with display name, username, label, value)
            $customers = UserDetail::where('is_delete', false)
                ->whereHas('role', function ($q) {
                    $q->whereIn('role', ['CUSTOMER', 'PURCHASE']); // CUSTOMER = retail customer, PURCHASE = retailer/buyer
                })
                ->select('user_id', 'name', 'user_name')
                ->orderBy('name')
                ->get()
                ->map(fn($c) => [
                    'id'            => $c->user_id,
                    'customer_id'   => $c->user_id,
                    'name'          => $c->name,
                    'customer_name' => $c->name,
                    'user_name'     => $c->user_name,
                    'label'         => $c->name . ($c->user_name ? " ({$c->user_name})" : ''),
                    'value'         => $c->user_id,
                ]);

            // 6. Active users / staff / operators for dropdown (with display name, username, label, value)
            $userIds = SystemLog::query()
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            $users = UserDetail::where('is_delete', false)
                ->when($userIds->isNotEmpty(), function ($q) use ($userIds) {
                    // Include both users who have logged actions and active users
                    $q->where(function ($sq) use ($userIds) {
                        $sq->whereIn('user_id', $userIds)
                           ->orWhere('is_active', true);
                    });
                })
                ->select('user_id', 'name', 'user_name')
                ->orderBy('name')
                ->limit(100)
                ->get()
                ->map(fn($u) => [
                    'id'        => $u->user_id,
                    'user_id'   => $u->user_id,
                    'name'      => $u->name,
                    'user_name' => $u->user_name,
                    'label'     => $u->name . ($u->user_name ? " ({$u->user_name})" : ''),
                    'value'     => $u->user_id,
                ]);

            // 7. Earliest and latest log dates
            $dateRange = [
                'min_date' => SystemLog::min('created_at'),
                'max_date' => SystemLog::max('created_at'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'System log filter options fetched successfully.',
                'data'    => [
                    'modules'           => $modules,
                    'actions_by_module' => $actionsByModule,
                    'all_actions'       => $allActions,
                    'reference_tables'  => $referenceTables,
                    'customers'         => $customers,
                    'users'             => $users,
                    'date_range'        => $dateRange,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filter options.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
