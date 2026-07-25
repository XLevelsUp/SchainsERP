<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    // GET: /api/roles
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ], 200);
    }

    // POST: /api/roles
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|max:50',
            'added_at' => 'nullable|date',
            'touch' => 'nullable|numeric',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    // GET: /api/roles/{id}
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

    // PUT: /api/roles/{id}
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
            'role' => 'sometimes|string|max:50',
            'added_at' => 'sometimes|nullable|date',
            'touch' => 'sometimes|nullable|numeric',
        ]);

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role->fresh()
        ], 200);
    }

    // DELETE: /api/roles/{id}
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