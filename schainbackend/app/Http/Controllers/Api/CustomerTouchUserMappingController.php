<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerTouchUserMapping;
use App\Http\Requests\UpdateCustomerTouchMappingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerTouchUserMappingController extends Controller
{
    /**
     * Display a listing of the customer touch user mappings.
     * Replaces legacy Yii2 `customer-touch-user-mappings/index` endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CustomerTouchUserMapping::with(['user', 'customerTouch']);

            // Optional filtering
            if ($request->has('user_id')) {
                $query->where('user_id', $request->query('user_id'));
            }

            $mappings = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Customer touch user mappings retrieved successfully',
                'data' => $mappings
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified mapping in storage.
     * Replaces legacy Yii2 `customer-touch-user-mappings/update` endpoint.
     */
    public function update(UpdateCustomerTouchMappingRequest $request, $id): JsonResponse
    {
        try {
            $mapping = CustomerTouchUserMapping::findOrFail($id);
            
            if ($request->has('user_id')) {
                $mapping->user_id = $request->user_id;
            }
            if ($request->has('customer_touch_id')) {
                $mapping->customer_touch_id = $request->customer_touch_id;
            }
            if ($request->has('is_active')) {
                $mapping->is_active = $request->is_active;
            }

            $mapping->save();

            return response()->json([
                'success' => true,
                'message' => 'Mapping updated successfully',
                'data' => $mapping
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mapping',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
