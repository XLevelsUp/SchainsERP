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
        $order = OrderDetail::create($request->all());
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
        $order->update($request->all());
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
