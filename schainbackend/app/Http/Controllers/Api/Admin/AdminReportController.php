<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockDetails;
use App\Rules\HeadUser;
use App\Services\ReportService;
use App\Support\ReportCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The stock reports in the admin console (agreed 2026-11-24): the same
 * ReportService logic the heads' app screens use, for every head.
 *
 *  - Items OB & CB and Consolidated are running balances from one head's
 *    side, so they're run per head (head_id required, a HEAD user); the
 *    console shows one section per head ("grouped by head").
 *  - One Day Action covers every head when head_id is left out.
 *  - ID Wise looks up any stock ID, whichever head it belongs to.
 *
 * Read-only; nothing here changes a balance.
 */
class AdminReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function itemsObcb(Request $request): JsonResponse
    {
        return $this->respond($request, true, 'Items OB & CB report compiled successfully.',
            fn (int $headId) => $this->reports->getItemsObcbReport($request->all(), $headId));
    }

    public function exportItemsObcb(Request $request): Response
    {
        return $this->export($request, fn (int $headId) => ReportCsv::itemsObcb(
            $this->reports->getItemsObcbReport(['is_export' => true] + $request->all(), $headId),
            "history_items_obcb_head_{$headId}.csv",
        ));
    }

    public function consolidated(Request $request): JsonResponse
    {
        return $this->respond($request, true, 'Consolidated report retrieved successfully.',
            fn (int $headId) => $this->reports->getConsolidatedReport($request->all(), $headId));
    }

    public function exportConsolidated(Request $request): Response
    {
        return $this->export($request, fn (int $headId) => ReportCsv::consolidated(
            $this->reports->getConsolidatedReport(['is_export' => true] + $request->all(), $headId),
            "consolidated_report_head_{$headId}.csv",
        ));
    }

    /** All heads unless head_id narrows it to one. Defaults to today, like the app. */
    public function oneDayAction(Request $request): JsonResponse
    {
        return $this->respond($request, false, 'One day action report fetched successfully.', function (?int $headId) use ($request) {
            $filters = $request->all();
            if (empty($filters['from_date']) && empty($filters['target_date'])) {
                $filters['from_date'] = date('Y-m-d');
            } elseif (!empty($filters['target_date'])) {
                $filters['from_date'] = $filters['target_date'];
            }

            return $this->reports->getOneDayActionReport($filters, $headId);
        });
    }

    public function idWise(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['stock_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $stock = StockDetails::find($request->integer('stock_id'));
        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Stock ID not found.'], 404);
        }

        try {
            // The service takes a head for its signature only; the lineage is
            // the same whoever looks at it.
            $result = $this->reports->getIdWiseStockReport($stock->stock_id, (int) ($stock->given_by ?: $stock->given_to));

            return response()->json(['success' => true, 'message' => 'ID-Wise stock report compiled successfully.', 'data' => $result], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Stock ID not found.'], 404);
        } catch (\Throwable $e) {
            Log::error('AdminReportController::idWise failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to compile ID-Wise stock report.'], 500);
        }
    }

    /** Validates head_id (a HEAD user; required or optional), then runs $run. */
    private function respond(Request $request, bool $headRequired, string $message, callable $run): JsonResponse
    {
        try {
            $headId = $this->headId($request, $headRequired);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        try {
            return response()->json(['success' => true, 'message' => $message, 'data' => $run($headId)], 200);
        } catch (\Throwable $e) {
            Log::error('AdminReportController failed', ['route' => $request->route()?->getName(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to compile the report.'], 500);
        }
    }

    private function export(Request $request, callable $run): Response
    {
        try {
            $headId = $this->headId($request, true);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        try {
            return $run($headId);
        } catch (\Throwable $e) {
            Log::error('AdminReportController export failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Export failed.'], 500);
        }
    }

    private function headId(Request $request, bool $required): ?int
    {
        Validator::make($request->all(), [
            'head_id' => [$required ? 'required' : 'nullable', 'integer', new HeadUser()],
        ], [
            'head_id.required' => 'Pick a head.',
        ])->validate();

        return $request->filled('head_id') ? $request->integer('head_id') : null;
    }
}
