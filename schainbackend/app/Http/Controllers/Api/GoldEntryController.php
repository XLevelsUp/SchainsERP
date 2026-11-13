<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashToGold;
use App\Services\GoldCorrectionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Gold entries (Cash to Gold, Gold to Cash, Purchase Gold, Sale Gold) for
 * correction by a head (BACKEND_TODO §17 phase 3). See GoldCorrectionService.
 */
class GoldEntryController extends Controller
{
    /**
     * GET /gold-entries/{id}: the entry, the input it was saved with (to
     * prefill the correction form), what may change, and whether it can be
     * corrected.
     */
    public function show(int $id, GoldCorrectionService $corrections): JsonResponse
    {
        $record = CashToGold::where('cash_to_gold_id', $id)->first();

        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Gold entry not found'], 404);
        }

        $kind = GoldCorrectionService::kindOf($record);

        return response()->json([
            'success' => true,
            'data' => [
                'record' => $record,
                'kind' => $kind,
                'input' => $kind ? $corrections->originalInput($record) : null,
                'editable_fields' => $kind ? GoldCorrectionService::EDITABLE[$kind] : [],
                'correct_blocked_reason' => GoldCorrectionService::notCorrectableReason($record),
            ],
        ]);
    }

    /**
     * POST /gold-entries/{id}/correct: reverses the entry and posts the
     * corrected one. Body: any of the entry's editable fields.
     */
    public function correct(Request $request, int $id, GoldCorrectionService $corrections): JsonResponse
    {
        try {
            $result = $corrections->correct($id, $request->all(), (int) $request->user()->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Gold entry corrected',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Gold entry not found'], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('GoldEntryController::correct failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to correct the gold entry.'], 500);
        }
    }
}
