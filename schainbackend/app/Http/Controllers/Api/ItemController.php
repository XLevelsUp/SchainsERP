<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Items',
    description: 'Items CRUD API'
)]
class ItemController extends Controller
{
    /**
     * GET ALL ITEMS
     */
    #[OA\Get(
        path: '/api/items',
        operationId: 'getItems',
        tags: ['Items'],
        summary: 'Get all items',
        description: 'Returns all items from the database',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Items retrieved successfully'
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $items = Item::orderBy('item_id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Items retrieved successfully',
            'data' => $items
        ], 200);
    }


    /**
     * CREATE ITEM
     */
    #[OA\Post(
        path: '/api/items',
        operationId: 'createItem',
        tags: ['Items'],
        summary: 'Create a new item',
        description: 'Create a new item in the database',

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['item_name'],

                properties: [
                    new OA\Property(
                        property: 'item_name',
                        type: 'string',
                        example: 'Gold Ring'
                    ),

                    new OA\Property(
                        property: 'is_active',
                        type: 'boolean',
                        example: true
                    ),

                    new OA\Property(
                        property: 'default_touch',
                        type: 'number',
                        format: 'float',
                        example: 92
                    ),

                    new OA\Property(
                        property: 'item_touch',
                        type: 'number',
                        format: 'float',
                        example: 90
                    ),

                    new OA\Property(
                        property: 'added_at',
                        type: 'string',
                        format: 'date-time',
                        example: '2026-07-23 10:30:00'
                    ),

                    new OA\Property(
                        property: 'is_need_fitem_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'mtouch',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 91.5
                    ),

                    new OA\Property(
                        property: 'is_barcode',
                        type: 'boolean',
                        example: true
                    ),

                    new OA\Property(
                        property: 'is_no_barcode',
                        type: 'boolean',
                        example: false
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: 'Item created successfully'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
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


    /**
     * GET SINGLE ITEM
     */
    #[OA\Get(
        path: '/api/items/{id}',
        operationId: 'getItemById',
        tags: ['Items'],
        summary: 'Get item by ID',
        description: 'Returns a single item by item ID',

        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Item ID',
                in: 'path',
                required: true,

                schema: new OA\Schema(
                    type: 'integer'
                ),

                example: 1
            )
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: 'Item retrieved successfully'
            ),

            new OA\Response(
                response: 404,
                description: 'Item not found'
            )
        ]
    )]
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


    /**
     * UPDATE ITEM
     */
    #[OA\Put(
        path: '/api/items/{id}',
        operationId: 'updateItem',
        tags: ['Items'],
        summary: 'Update an item',
        description: 'Update an existing item by item ID',

        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Item ID',
                in: 'path',
                required: true,

                schema: new OA\Schema(
                    type: 'integer'
                ),

                example: 1
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,

            content: new OA\JsonContent(

                properties: [
                    new OA\Property(
                        property: 'item_name',
                        type: 'string',
                        example: 'Updated Gold Ring'
                    ),

                    new OA\Property(
                        property: 'is_active',
                        type: 'boolean',
                        example: true
                    ),

                    new OA\Property(
                        property: 'default_touch',
                        type: 'number',
                        format: 'float',
                        example: 92
                    ),

                    new OA\Property(
                        property: 'item_touch',
                        type: 'number',
                        format: 'float',
                        example: 91
                    ),

                    new OA\Property(
                        property: 'is_need_fitem_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'mtouch',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 91.5
                    ),

                    new OA\Property(
                        property: 'is_barcode',
                        type: 'boolean',
                        example: true
                    ),

                    new OA\Property(
                        property: 'is_no_barcode',
                        type: 'boolean',
                        example: false
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: 'Item updated successfully'
            ),

            new OA\Response(
                response: 404,
                description: 'Item not found'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
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
            'data' => $item
        ], 200);
    }


    /**
     * DELETE ITEM
     */
    #[OA\Delete(
        path: '/api/items/{id}',
        operationId: 'deleteItem',
        tags: ['Items'],
        summary: 'Delete an item',
        description: 'Delete an item by item ID',

        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Item ID',
                in: 'path',
                required: true,

                schema: new OA\Schema(
                    type: 'integer'
                ),

                example: 1
            )
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: 'Item deleted successfully'
            ),

            new OA\Response(
                response: 404,
                description: 'Item not found'
            )
        ]
    )]
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