<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FitemBox;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Fitem Boxes',
    description: 'Fitem Boxes CRUD API'
)]
class FitemBoxController extends Controller
{
    /**
     * Get all Fitem Boxes
     */
    #[OA\Get(
        path: '/api/fitem-boxes',
        operationId: 'getFitemBoxes',
        tags: ['Fitem Boxes'],
        summary: 'Get all Fitem boxes',
        description: 'Returns a list of all Fitem boxes.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fitem boxes retrieved successfully'
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $boxes = FitemBox::with('item')
            ->orderBy('box_id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Fitem boxes retrieved successfully',
            'data' => $boxes
        ], 200);
    }


    /**
     * Create Fitem Box
     */
    #[OA\Post(
        path: '/api/fitem-boxes',
        operationId: 'createFitemBox',
        tags: ['Fitem Boxes'],
        summary: 'Create a new Fitem box',
        description: 'Creates a new Fitem box.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'box_name',
                    'item_id',
                    'added_by'
                ],
                properties: [
                    new OA\Property(
                        property: 'box_name',
                        type: 'string',
                        maxLength: 100,
                        example: 'Gold Box'
                    ),
                    new OA\Property(
                        property: 'item_id',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'is_active',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'added_by',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'updated_by',
                        type: 'integer',
                        nullable: true,
                        example: null
                    ),
                    new OA\Property(
                        property: 'added_at',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        example: '2026-07-23 10:30:00'
                    ),
                    new OA\Property(
                        property: 'updated_at',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        example: '2026-07-23 10:30:00'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Fitem box created successfully'
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
            'box_name' => 'required|string|max:100',
            'item_id' => 'required|integer|exists:items,item_id',
            'is_active' => 'sometimes|boolean',
            'added_by' => 'required|integer',
            'updated_by' => 'nullable|integer',
            'added_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date',
        ]);

        $box = FitemBox::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fitem box created successfully',
            'data' => $box
        ], 201);
    }


    /**
     * Get Fitem Box by ID
     */
    #[OA\Get(
        path: '/api/fitem-boxes/{id}',
        operationId: 'getFitemBoxById',
        tags: ['Fitem Boxes'],
        summary: 'Get Fitem box by ID',
        description: 'Returns a single Fitem box by its ID.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Fitem Box ID',
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
                description: 'Fitem box retrieved successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'Fitem box not found'
            )
        ]
    )]
    public function show($id): JsonResponse
    {
        $box = FitemBox::with('item')
            ->find($id);

        if (!$box) {
            return response()->json([
                'success' => false,
                'message' => 'Fitem box not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fitem box retrieved successfully',
            'data' => $box
        ], 200);
    }


    /**
     * Update Fitem Box
     */
    #[OA\Put(
        path: '/api/fitem-boxes/{id}',
        operationId: 'updateFitemBox',
        tags: ['Fitem Boxes'],
        summary: 'Update an Fitem box',
        description: 'Updates an existing Fitem box.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Fitem Box ID',
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
                        property: 'box_name',
                        type: 'string',
                        maxLength: 100,
                        example: 'Updated Gold Box'
                    ),
                    new OA\Property(
                        property: 'item_id',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'is_active',
                        type: 'boolean',
                        example: true
                    ),
                    new OA\Property(
                        property: 'added_by',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'updated_by',
                        type: 'integer',
                        nullable: true,
                        example: 2
                    ),
                    new OA\Property(
                        property: 'updated_at',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        example: '2026-07-23 11:30:00'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fitem box updated successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'Fitem box not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $box = FitemBox::find($id);

        if (!$box) {
            return response()->json([
                'success' => false,
                'message' => 'Fitem box not found'
            ], 404);
        }

        $validated = $request->validate([
            'box_name' => 'sometimes|required|string|max:100',
            'item_id' => 'sometimes|required|integer|exists:items,item_id',
            'is_active' => 'sometimes|boolean',
            'added_by' => 'sometimes|required|integer',
            'updated_by' => 'nullable|integer',
            'added_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date',
        ]);

        $box->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Fitem box updated successfully',
            'data' => $box
        ], 200);
    }


    /**
     * Delete Fitem Box
     */
    #[OA\Delete(
        path: '/api/fitem-boxes/{id}',
        operationId: 'deleteFitemBox',
        tags: ['Fitem Boxes'],
        summary: 'Delete an Fitem box',
        description: 'Deletes an existing Fitem box.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Fitem Box ID',
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
                description: 'Fitem box deleted successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'Fitem box not found'
            )
        ]
    )]
    public function destroy($id): JsonResponse
    {
        $box = FitemBox::find($id);

        if (!$box) {
            return response()->json([
                'success' => false,
                'message' => 'Fitem box not found'
            ], 404);
        }

        $box->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fitem box deleted successfully'
        ], 200);
    }
}