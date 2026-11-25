<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The query of the Item Change History and Item Conversion Group History
 * pages (app and admin console, 2026-11-25): From / To Date (today by
 * default; To Date left out means one day), Item (either side of the
 * change or conversion) and paging.
 */
final class ItemHistoryFilters
{
    public const MAX_PAGE_SIZE = 1000;

    /** A 422 response for bad input, or null when the query is fine. */
    public static function invalid(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'item_id' => 'nullable|integer',
            'page_no' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:' . self::MAX_PAGE_SIZE,
        ], [
            'to_date.after_or_equal' => 'To Date must be on or after From Date.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        return null;
    }

    /** The filters for ItemHistoryReportService. */
    public static function from(Request $request): array
    {
        return $request->only(['from_date', 'to_date', 'item_id', 'page_no', 'page_size']);
    }
}
