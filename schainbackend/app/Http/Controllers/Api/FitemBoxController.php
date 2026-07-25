<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FitemBox;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FitemBoxController extends Controller
{
    // GET: /api/fitem-boxes
    public function index(): JsonResponse
    {
        $boxes = FitemBox::orderBy('box_id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Fitem boxes retrieved successfully',
            'data' => $boxes
        ], 200);
    }


    // POST: /api/fitem-boxes
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'box_name' => 'required|string|max:100',
            'item_id' => 'required|integer|exists:items,item_id',
            'is_active' => 'required|boolean',
            'added_by' => 'required|integer',
        ]);

        $box = FitemBox::create([
            'box_name' => $validated['box_name'],
            'item_id' => $validated['item_id'],
            'is_active' => $validated['is_active'],
            'added_by' => $validated['added_by'],
            'added_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Fitem box created successfully',
            'data' => $box
        ], 201);
    }


    // GET: /api/fitem-boxes/{box_id}
    public function show($box_id): JsonResponse
    {
        $box = FitemBox::find($box_id);

        if (!$box) {
            return response()->json([
                'status' => false,
                'message' => 'Fitem box not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Fitem box retrieved successfully',
            'data' => $box
        ], 200);
    }


    // PUT: /api/fitem-boxes/{box_id}
    public function update(Request $request, $box_id): JsonResponse
    {
        $box = FitemBox::find($box_id);

        if (!$box) {
            return response()->json([
                'status' => false,
                'message' => 'Fitem box not found'
            ], 404);
        }

        $validated = $request->validate([
            'box_name' => 'required|string|max:100',
            'item_id' => 'required|integer|exists:items,item_id',
            'is_active' => 'required|boolean',
            'updated_by' => 'nullable|integer',
        ]);

        $box->update([
            'box_name' => $validated['box_name'],
            'item_id' => $validated['item_id'],
            'is_active' => $validated['is_active'],
            'updated_by' => $validated['updated_by'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Fitem box updated successfully',
            'data' => $box->fresh()
        ], 200);
    }


    // DELETE: /api/fitem-boxes/{box_id}
    public function destroy($box_id): JsonResponse
    {
        $box = FitemBox::find($box_id);

        if (!$box) {
            return response()->json([
                'status' => false,
                'message' => 'Fitem box not found'
            ], 404);
        }

        $box->delete();

        return response()->json([
            'status' => true,
            'message' => 'Fitem box deleted successfully'
        ], 200);
    }
}