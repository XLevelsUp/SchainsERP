<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Roles',
    description: 'Roles CRUD API'
)]
class RoleController extends Controller
{
    /**
     * Get all roles
     */
    #[OA\Get(
        path: '/api/roles',
        operationId: 'getRoles',
        tags: ['Roles'],
        summary: 'Get all roles',
        description: 'Returns all roles from the database',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Roles retrieved successfully'
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ], 200);
    }


    /**
     * Create a new role
     */
    #[OA\Post(
        path: '/api/roles',
        operationId: 'createRole',
        tags: ['Roles'],
        summary: 'Create a new role',
        description: 'Create a new role in the database',

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(
                        property: 'role',
                        type: 'string',
                        maxLength: 50,
                        example: 'Admin'
                    ),

                    new OA\Property(
                        property: 'added_at',
                        type: 'string',
                        format: 'date-time',
                        example: '2026-07-23 10:30:00'
                    ),

                    new OA\Property(
                        property: 'touch',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 92.5
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: 'Role created successfully'
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
            'role' => 'required|string|max:50',

            'added_at' => 'sometimes|date',

            'touch' => 'nullable|numeric',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }


    /**
     * Get single role
     */
    #[OA\Get(
        path: '/api/roles/{id}',
        operationId: 'getRoleById',
        tags: ['Roles'],
        summary: 'Get role by ID',
        description: 'Returns a single role by ID',

        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
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
                description: 'Role retrieved successfully'
            ),

            new OA\Response(
                response: 404,
                description: 'Role not found'
            )
        ]
    )]
    public function show($id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully',
            'data' => $role
        ], 200);
    }


    /**
     * Update role
     */
    #[OA\Put(
        path: '/api/roles/{id}',
        operationId: 'updateRole',
        tags: ['Roles'],
        summary: 'Update a role',
        description: 'Update an existing role by ID',

        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
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
                        property: 'role',
                        type: 'string',
                        maxLength: 50,
                        example: 'Manager'
                    ),

                    new OA\Property(
                        property: 'added_at',
                        type: 'string',
                        format: 'date-time',
                        example: '2026-07-23 10:30:00'
                    ),

                    new OA\Property(
                        property: 'touch',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 90.5
                    )
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated successfully'
            ),

            new OA\Response(
                response: 404,
                description: 'Role not found'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|required|string|max:50',

            'added_at' => 'sometimes|date',

            'touch' => 'nullable|numeric',
        ]);

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ], 200);
    }


    /**
     * Delete role
     */
    #[OA\Delete(
        path: '/api/roles/{id}',
        operationId: 'deleteRole',
        tags: ['Roles'],
        summary: 'Delete a role',
        description: 'Delete a role by ID',

        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Role ID',
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
                description: 'Role deleted successfully'
            ),

            new OA\Response(
                response: 404,
                description: 'Role not found'
            )
        ]
    )]
    public function destroy($id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ], 200);
    }
}