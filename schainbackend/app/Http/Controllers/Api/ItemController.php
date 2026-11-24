<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\UsersItemsMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    /** Where an item can be referenced, with a label for the message. */
    private const REFERENCES = [
        'users_items_mappings' => [['item_id'], "users' items"],
        'stock_details' => [['item_id', 'to_item_id'], 'stock entries'],
        'stock_in_details' => [['item_id', 'to_item_id'], 'stock entries'],
        'fitem_boxes' => [['item_id'], 'fitem boxes'],
        'item_change_history' => [['from_item_id', 'to_item_id'], 'item changes'],
        'gms_history' => [['item_id', 'to_item_id'], 'GMS history'],
        'gms_in_histories' => [['item_id'], 'GMS history'],
        'numeric_wastages' => [['item_id'], 'wastage entries'],
        'numeric_wastage_in_records' => [['item_id'], 'wastage entries'],
        'gold_conversions' => [['source_item_id', 'target_item_id'], 'gold conversions'],
        'gold_conversion_alloys' => [['alloy_item_id'], 'gold conversions'],
        'opening_balance_updates' => [['item_id'], 'opening balance updates'],
    ];

    /** The places an item is still used, as text; '' when unused. */
    private static function usedIn(int $itemId): string
    {
        $found = [];
        foreach (self::REFERENCES as $table => [$columns, $label]) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_filter($columns, fn ($c) => \Illuminate\Support\Facades\Schema::hasColumn($table, $c)));
            if ($columns === []) {
                continue;
            }
            $used = \Illuminate\Support\Facades\DB::table($table)->where(function ($q) use ($columns, $itemId) {
                foreach ($columns as $column) {
                    $q->orWhere($column, $itemId);
                }
            })->exists();
            if ($used) {
                $found[$label] = true;
            }
        }

        return implode(', ', array_keys($found));
    }

    private const ORDER_MESSAGES = [
        'order_no.unique' => 'Another item already has this order number.',
        'order_no.min' => 'The order number must be 1 or more.',
        'order_no.integer' => 'The order number must be a whole number.',
    ];

    // GET: /api/items
    // ?mapped_only=1 (the Stock screen's pickers): only the items mapped to
    // the signed-in user in users_items_mappings. Without it, every item.
    public function index(Request $request): JsonResponse
    {
        // Every item dropdown and list follows the Items page's order number
        // (2026-11-24); ties, if any, alphabetically.
        $query = Item::orderBy('order_no')->orderBy('item_name');

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
            'order_no' => 'nullable|integer|min:1|max:1000000|unique:items,order_no',
        ], self::ORDER_MESSAGES);

        // A new item takes the next free number unless one is given.
        $validated['order_no'] ??= ((int) Item::max('order_no')) + 1;

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
            'order_no' => 'sometimes|required|integer|min:1|max:1000000|unique:items,order_no,' . $item->item_id . ',item_id',
        ], self::ORDER_MESSAGES);

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

        // Deleting an item cascades into users' item balances and the stock
        // history (2026-11-24), so an item still in use can't be deleted:
        // switch it off (Is Active = No) instead.
        if ($usedIn = self::usedIn((int) $item->item_id)) {
            return response()->json([
                'success' => false,
                'message' => "{$item->item_name} is still used ({$usedIn}), so it can't be deleted. Switch it off instead (Is Active = No).",
                'errors' => ['item' => ["{$item->item_name} is still used ({$usedIn}). Switch it off instead."]],
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ], 200);
    }
}