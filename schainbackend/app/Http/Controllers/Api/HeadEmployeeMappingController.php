<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeadEmployeeMapping;
use App\Http\Requests\StoreHeadEmployeeMappingRequest;
use App\Http\Requests\UpdateHeadEmployeeMappingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HeadEmployeeMappingController extends Controller
{
    /**
     * GET ALL HEAD EMPLOYEE MAPPINGS (Grouped by Employee)
     */
    public function index(Request $request): JsonResponse
    {
        $query = HeadEmployeeMapping::with(['head', 'employee']);

        // Filter by employee's active status if provided
        if ($request->has('employee_is_active')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('is_active', $request->boolean('employee_is_active'));
            });
        }

        // Filter by head's active status if provided
        if ($request->has('head_is_active')) {
            $query->whereHas('head', function ($q) use ($request) {
                $q->where('is_active', $request->boolean('head_is_active'));
            });
        }

        $mappings = $query->get();

        // Group by employee_id to match UI expectations
        $grouped = $mappings->groupBy('employee_id')->map(function ($employeeMappings) {
            $employee = $employeeMappings->first()->employee;
            
            return [
                'employee_id' => $employeeMappings->first()->employee_id,
                'employee_name' => $employee ? $employee->name : null,
                'heads' => $employeeMappings->map(function ($map) {
                    return [
                        'mapping_id' => $map->id,
                        'head_id' => $map->head_id,
                        'head_name' => $map->head ? $map->head->name : null,
                        'added_at' => (string)$map->added_at,
                    ];
                })->values()->toArray()
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Head employee mappings retrieved successfully',
            'data' => $grouped
        ], 200);
    }

    /**
     * CREATE HEAD EMPLOYEE MAPPING (Bulk Heads for 1 Employee)
     */
    public function store(StoreHeadEmployeeMappingRequest $request): JsonResponse
    {
        $employeeId = $request->input('employee_id');
        $headIds = $request->input('head_ids');
        
        DB::beginTransaction();
        try {
            $createdMappings = [];
            
            foreach ($headIds as $headId) {
                // Avoid creating duplicates if it already exists
                $exists = HeadEmployeeMapping::where('employee_id', $employeeId)
                    ->where('head_id', $headId)
                    ->exists();
                    
                if (!$exists) {
                    $createdMappings[] = HeadEmployeeMapping::create([
                        'head_id' => $headId,
                        'employee_id' => $employeeId,
                        'added_at' => now(),
                    ]);
                }
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Head employee mappings created successfully',
                'data' => $createdMappings
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPDATE MAPPINGS (Delete unselected, insert new)
     */
    public function update(UpdateHeadEmployeeMappingRequest $request, $employeeId): JsonResponse
    {
        $headIds = $request->input('head_ids'); // The new set of head_ids
        
        DB::beginTransaction();
        try {
            // Delete mappings not in the new head_ids array
            HeadEmployeeMapping::where('employee_id', $employeeId)
                ->whereNotIn('head_id', $headIds)
                ->delete();

            // Insert new ones that don't exist yet
            foreach ($headIds as $headId) {
                $exists = HeadEmployeeMapping::where('employee_id', $employeeId)
                    ->where('head_id', $headId)
                    ->exists();
                    
                if (!$exists) {
                    HeadEmployeeMapping::create([
                        'head_id' => $headId,
                        'employee_id' => $employeeId,
                        'added_at' => now(),
                    ]);
                }
            }
            
            DB::commit();

            // Fetch updated mappings for this employee
            $updatedMappings = HeadEmployeeMapping::with(['head'])->where('employee_id', $employeeId)->get();
            
            $formattedMappings = $updatedMappings->map(function ($map) {
                return [
                    'id' => $map->id,
                    'head_id' => $map->head_id,
                    'employee_id' => $map->employee_id,
                    'added_at' => (string)$map->added_at,
                    'head' => $map->head ? [
                        'user_id' => $map->head->user_id,
                        'name' => $map->head->name,
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Head employee mappings updated successfully',
                'data' => $formattedMappings
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}