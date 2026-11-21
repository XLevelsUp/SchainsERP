<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\UsersItemsMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    // GET: /api/items
    // ?mapped_only=1 (the Stock screen's pickers): only the items mapped to
    // the signed-in user in users_items_mappings. Without it, every item.
    public function index(Request $request): JsonResponse
    {
        $query = Item::orderBy('item_id', 'desc');

        if ($request->boolean('mapped_only')) {
            $query->whereIn(
                'item_id',
                UsersItemsMapping::where('user_id', $request->user()->user_id)->select('item_id')
            );
        }

        $items = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Items retrieved successfully',
            'data' => $items
        ], 200);
    }

    // POST: /api/items
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'default_touch' => 'sometimes|numeric',
            'item_touch' => 'sometimes|numeric',
            'added_at' => 'sometimes|date',
            'is_need_fitem_shown' => 'sometimes|boolean',
            'mtouch' => 'nullable|numeric',
            'is_barcode' => 'sometimes|boolean',
            'is_no_barcode' => 'sometimes|boolean',
        ]);

        $item = Item::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item created successfully',
            'data' => $item
        ], 201);
    }

    // GET: /api/items/{id}
    public function show($id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item retrieved successfully',
            'data' => $item
        ], 200);
    }

    // PUT: /api/items/{id}
    public function update(Request $request, $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $validated = $request->validate([
            'item_name' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'default_touch' => 'sometimes|numeric',
            'item_touch' => 'sometimes|numeric',
            'added_at' => 'sometimes|date',
            'is_need_fitem_shown' => 'sometimes|boolean',
            'mtouch' => 'nullable|numeric',
            'is_barcode' => 'sometimes|boolean',
            'is_no_barcode' => 'sometimes|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'data' => $item->fresh()
        ], 200);
    }

    // DELETE: /api/items/{id}
    public function destroy($id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ], 200);
    }
}