<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => OrderDetail::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:user_details,user_id',
            'item_id' => 'required|exists:items,item_id',
            'status' => 'required|in:PENDING,PROCESSING,COMPLETED,CANCELLED',
            'grams' => 'nullable|numeric',
            'remarks' => 'nullable|string',
            'due_date' => 'nullable|date'
        ]);

        $order = OrderDetail::create($validated);
        return response()->json([
            'success' => true,
            'data' => $order
        ], 201);
    }
    
    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => OrderDetail::findOrFail($id)
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $order = OrderDetail::findOrFail($id);
        
        $validated = $request->validate([
            'customer_id' => 'sometimes|exists:user_details,user_id',
            'item_id' => 'sometimes|exists:items,item_id',
            'status' => 'sometimes|in:PENDING,PROCESSING,COMPLETED,CANCELLED',
            'grams' => 'nullable|numeric',
            'remarks' => 'nullable|string',
            'due_date' => 'nullable|date'
        ]);

        $order->update($validated);
        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
    
    public function destroy($id)
    {
        $order = OrderDetail::findOrFail($id);
        $order->delete();
        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}
