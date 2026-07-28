<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeadEmployeeMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HeadEmployeeMappingController extends Controller
{
    /**
     * GET ALL HEAD EMPLOYEE MAPPINGS
     */
    public function index(): JsonResponse
    {
        $mappings = HeadEmployeeMapping::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Head employee mappings retrieved successfully',
            'data' => $mappings
        ], 200);
    }

    /**
     * CREATE HEAD EMPLOYEE MAPPING
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'head_id' => [
                'required',
                'integer',
                'exists:user_details,user_id'
            ],

            'employee_id' => [
                'required',
                'integer',
                'exists:user_details,user_id'
            ],
        ]);

        $mapping = HeadEmployeeMapping::create([
            'head_id' => $validated['head_id'],
            'employee_id' => $validated['employee_id'],
            'added_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Head employee mapping created successfully',
            'data' => $mapping
        ], 201);
    }

    /**
     * GET SINGLE MAPPING
     */
    public function show($id): JsonResponse
    {
        $mapping = HeadEmployeeMapping::find($id);

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Head employee mapping not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Head employee mapping retrieved successfully',
            'data' => $mapping
        ], 200);
    }

    /**
     * UPDATE MAPPING
     */
    public function update(Request $request, $id): JsonResponse
    {
        $mapping = HeadEmployeeMapping::find($id);

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Head employee mapping not found'
            ], 404);
        }

        $validated = $request->validate([
            'head_id' => [
                'sometimes',
                'integer',
                'exists:user_details,user_id'
            ],

            'employee_id' => [
                'sometimes',
                'integer',
                'exists:user_details,user_id'
            ],
        ]);

        $mapping->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Head employee mapping updated successfully',
            'data' => $mapping->fresh()
        ], 200);
    }

    /**
     * DELETE MAPPING
     */
    public function destroy($id): JsonResponse
    {
        $mapping = HeadEmployeeMapping::find($id);

        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Head employee mapping not found'
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Head employee mapping deleted successfully'
        ], 200);
    }
}