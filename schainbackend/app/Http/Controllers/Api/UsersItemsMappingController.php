<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersItemsMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UsersItemsMappingController extends Controller
{
    /**
     * GET ALL MAPPINGS
     */
    public function index(): JsonResponse
    {
        $mappings = UsersItemsMapping::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Users items mappings retrieved successfully',
            'data' => $mappings
        ], 200);
    }

    /**
     * CREATE MAPPING
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:items,item_id',
            'user_id' => 'required|integer|exists:user_details,user_id',

            'item_grams_total' => 'nullable|string',
            'item_purity_total' => 'nullable|string',

            'stone_cost_grand_total' => 'nullable|numeric',
            'beads_cost_grand_total' => 'nullable|numeric',
            'beads_stone_cost_grand_total' => 'nullable|numeric',

            'last_txn_date' => 'nullable|date',

            'is_primary' => 'required|integer',
        ]);

        $mapping = UsersItemsMapping::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User item mapping created successfully',
            'data' => $mapping
        ], 201);
    }

    /**
     * GET MAPPING BY ID
     */
    public function show($id): JsonResponse
    {
        $mapping = UsersItemsMapping::find($id);

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'User item mapping not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User item mapping retrieved successfully',
            'data' => $mapping
        ], 200);
    }

    /**
     * UPDATE MAPPING
     */
    public function update(
        Request $request,
        $id
    ): JsonResponse {

        $mapping = UsersItemsMapping::find($id);

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'User item mapping not found'
            ], 404);
        }

        $validated = $request->validate([
            'item_id' => 'sometimes|integer|exists:items,item_id',
            'user_id' => 'sometimes|integer|exists:user_details,user_id',

            'item_grams_total' => 'nullable|string',
            'item_purity_total' => 'nullable|string',

            'stone_cost_grand_total' => 'nullable|numeric',
            'beads_cost_grand_total' => 'nullable|numeric',
            'beads_stone_cost_grand_total' => 'nullable|numeric',

            'last_txn_date' => 'nullable|date',

            'is_primary' => 'sometimes|integer',
        ]);

        $mapping->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User item mapping updated successfully',
            'data' => $mapping->fresh()
        ], 200);
    }

    /**
     * DELETE MAPPING
     */
    public function destroy($id): JsonResponse
    {
        $mapping = UsersItemsMapping::find($id);

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'User item mapping not found'
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'User item mapping deleted successfully'
        ], 200);
    }
}