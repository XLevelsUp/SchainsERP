<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesRecordAccess;
use App\Http\Controllers\Controller;
use App\Services\ItemHistoryReportService;
use App\Support\ItemHistoryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Item Change History and Item Conversion Group History in the app
 * (2026-11-25): the signed-in user's own records, plus their mapped
 * employees' for a head. Each needs the permission of its Stocks page
 * feature (routes/api.php). The console's are in AdminReportController.
 */
class ItemHistoryReportController extends Controller
{
    use ScopesRecordAccess;

    public function __construct(private readonly ItemHistoryReportService $reports)
    {
    }

    public function itemChanges(Request $request): JsonResponse
    {
        return $this->respond($request, 'Item change history fetched successfully.',
            fn (array $filters, array $userIds) => $this->reports->itemChanges($filters, $userIds));
    }

    public function conversionGroups(Request $request): JsonResponse
    {
        return $this->respond($request, 'Item conversion group history fetched successfully.',
            fn (array $filters, array $userIds) => $this->reports->conversionGroups($filters, $userIds));
    }

    private function respond(Request $request, string $message, callable $run): JsonResponse
    {
        if ($invalid = ItemHistoryFilters::invalid($request)) {
            return $invalid;
        }

        try {
            $userIds = $this->reports->scopeFor($this->actingUserId($request));

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $run(ItemHistoryFilters::from($request), $userIds),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('ItemHistoryReportController failed', ['path' => $request->path(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to compile the report.'], 500);
        }
    }
}
