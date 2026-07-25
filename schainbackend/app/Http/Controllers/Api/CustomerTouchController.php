<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerTouch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerTouchController extends Controller
{
    // GET ALL
    public function index(): JsonResponse
    {
        $items = CustomerTouch::orderBy('item_id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Customer touch items retrieved successfully',
            'data' => $items
        ], 200);
    }

    // CREATE
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:150',
            'is_active' => 'sometimes|boolean',
        ]);

        $item = CustomerTouch::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer touch item created successfully',
            'data' => $item
        ], 201);
    }

    // GET BY ID
    public function show($item_id): JsonResponse
    {
        $item = CustomerTouch::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Customer touch item not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer touch item retrieved successfully',
            'data' => $item
        ], 200);
    }

    // UPDATE
    public function update(Request $request, $item_id): JsonResponse
    {
        $item = CustomerTouch::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Customer touch item not found'
            ], 404);
        }

        $validated = $request->validate([
            'item_name' => 'sometimes|required|string|max:150',
            'is_active' => 'sometimes|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer touch item updated successfully',
            'data' => $item->fresh()
        ], 200);
    }

    // DELETE
    public function destroy($item_id): JsonResponse
    {
        $item = CustomerTouch::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Customer touch item not found'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer touch item deleted successfully'
        ], 200);
    }
}