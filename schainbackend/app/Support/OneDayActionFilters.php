<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The One Day Action report's query (app and admin console, 2026-11-25).
 *
 * The filters follow the legacy screen: User (who keyed the entry,
 * `employee_id`), Items, Stock Type (IN / OUT), Given By, Given To, Role
 * (a party's role), From / To Date. The report has two sections, stock and
 * cash; `section` fetches one of them, for its Load More.
 */
final class OneDayActionFilters
{
    public const MAX_PAGE_SIZE = 1000;

    /** A 422 response for bad input, or null when the query is fine. */
    public static function invalid(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'employee_id' => 'nullable|integer',
            'item_id' => 'nullable|integer',
            'given_by' => 'nullable|integer',
            'given_to' => 'nullable|integer',
            'given_by_given_to' => 'nullable|integer',
            'retailer_id' => 'nullable|integer',
            'role_id' => 'nullable|integer',
            'type' => 'nullable|in:IN,OUT',
            'stocktype' => 'nullable|string|max:40',
            'section' => 'nullable|in:stock,cash',
            'page_no' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:' . self::MAX_PAGE_SIZE,
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        return null;
    }

    /** The filters for ReportService, with the date defaulting to today. */
    public static function from(Request $request): array
    {
        $filters = $request->all();

        if (!empty($filters['target_date'])) {
            $filters['from_date'] = $filters['target_date'];
        } elseif (empty($filters['from_date'])) {
            $filters['from_date'] = date('Y-m-d');
        }

        return $filters;
    }
}
