<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCategory;
use App\Http\Requests\StoreCashCategoryRequest;
use App\Http\Requests\UpdateCashCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 50);
        
        $categories = CashCategory::orderByDesc('category_id')
            ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Cash categories fetched successfully.',
            'data' => [
                'total_count' => $categories->total(),
                'page_no' => $categories->currentPage(),
                'page_size' => $categories->perPage(),
                'records' => $categories->items(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCashCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Add the user who created it
        $data['added_by'] = $request->header('X-User-ID', 1);

        $category = CashCategory::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cash category created successfully.',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $category = CashCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Cash category not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cash category fetched successfully.',
            'data' => $category
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCashCategoryRequest $request, $id): JsonResponse
    {
        $category = CashCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Cash category not found.'
            ], 404);
        }

        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cash category updated successfully.',
            'data' => $category->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage (Soft Delete).
     */
    public function destroy($id): JsonResponse
    {
        $category = CashCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Cash category not found.'
            ], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cash category deleted successfully.'
        ]);
    }
}
