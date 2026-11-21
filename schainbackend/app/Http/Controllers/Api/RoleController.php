<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    // GET: /api/roles — all roles, inactive included (the Users, Phone Book
    // and Cash Management filters still need them; pickers hide inactive
    // ones for new assignments).
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles
        ], 200);
    }

    /*
    | Role names are stored trimmed and in capitals, like the existing ones
    | (HEAD, EMPLOYEE, CUSTOMER, ...), and must be unique ignoring case.
    | Several checks look roles up by exact name (e.g. 'CUSTOMER'), so a
    | second "Head" or a mixed-case "Customer" would quietly break them.
    */
    private function normaliseRoleName(Request $request): void
    {
        if ($request->has('role') && is_string($request->input('role'))) {
            $request->merge(['role' => mb_strtoupper(trim($request->input('role')))]);
        }
    }

    private function uniqueRoleName(?int $ignoreId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId) {
            $taken = Role::whereRaw('UPPER(role) = ?', [mb_strtoupper((string) $value)])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($taken) {
                $fail('A role with this name already exists.');
            }
        };
    }

    // POST: /api/roles (admin console, routes/admin.php)
    public function store(Request $request): JsonResponse
    {
        $this->normaliseRoleName($request);

        $validated = $request->validate([
            'role' => ['required', 'string', 'max:50', $this->uniqueRoleName()],
            'added_at' => 'nullable|date',
            'touch' => 'nullable|numeric',
            // BACKEND_TODO §14. Omitted means active (the column default).
            'is_active' => 'sometimes|boolean',
        ]);

        // fresh(): return the stored row, column defaults (is_active, added_at) included.
        $role = Role::create($validated)->fresh();

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

        $this->normaliseRoleName($request);

        $validated = $request->validate([
            'role' => ['sometimes', 'string', 'max:50', $this->uniqueRoleName($role->id)],
            'added_at' => 'sometimes|nullable|date',
            'touch' => 'sometimes|nullable|numeric',
            'is_active' => 'sometimes|boolean',
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