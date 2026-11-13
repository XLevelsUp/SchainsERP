<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NumericWastageCorrectionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Numeric wastage entries (in / out) for correction by a head
 * (BACKEND_TODO §17). {id} is the entry's stock row id, the "ID : N" its
 * cash entry's remarks show. See NumericWastageCorrectionService.
 */
class NumericWastageEntryController extends Controller
{
    /**
     * GET /numeric-wastage-entries/{direction}/{id}: the entry, the line it
     * was saved with (to prefill the correction form), what may change, and
     * whether it can be corrected.
     */
    public function show(string $direction, int $id, NumericWastageCorrectionService $corrections): JsonResponse
    {
        $stock = NumericWastageCorrectionService::stockRow($direction, $id);

        if (!$stock) {
            return response()->json(['success' => false, 'message' => 'Numeric wastage entry not found'], 404);
        }

        $record = NumericWastageCorrectionService::wastageRecord($direction, $stock);

        return response()->json([
            'success' => true,
            'data' => [
                'direction' => $direction,
                'record' => $stock,
                'wastage' => $record,
                'input' => $record ? $corrections->originalInput($stock, $record) : null,
                'editable_fields' => NumericWastageCorrectionService::EDITABLE,
                'correct_blocked_reason' => NumericWastageCorrectionService::notCorrectableReason($stock, $record),
            ],
        ]);
    }

    /**
     * POST /numeric-wastage-entries/{direction}/{id}/correct: reverses the
     * entry and posts the corrected one. Body: any of the editable fields.
     */
    public function correct(Request $request, string $direction, int $id, NumericWastageCorrectionService $corrections): JsonResponse
    {
        try {
            $result = $corrections->correct($direction, $id, $request->all(), (int) $request->user()->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Numeric wastage entry corrected',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Numeric wastage entry not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('NumericWastageEntryController::correct failed', ['direction' => $direction, 'id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to correct the numeric wastage entry.'], 500);
        }
    }
}
