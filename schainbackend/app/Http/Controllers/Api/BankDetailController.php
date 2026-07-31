<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankDetailController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL BANKS
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        try {
            $banks = BankDetail::orderBy('bank_id', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Banks retrieved successfully',
                'data' => $banks
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve banks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE BANK
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:150',
            'current_balance' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bank = BankDetail::create([
                'bank_name' => $request->bank_name,
                'current_balance' => $request->input('current_balance', 0),
                'is_active' => $request->input('is_active', true),
                'added_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank created successfully',
                'data' => $bank
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET ONE BANK
    |--------------------------------------------------------------------------
    */

    public function show($id): JsonResponse
    {
        try {
            $bank = BankDetail::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bank retrieved successfully',
                'data' => $bank
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE BANK
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $bank = BankDetail::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'bank_name' => 'sometimes|required|string|max:150',
                'current_balance' => 'sometimes|numeric',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $bank->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Bank updated successfully',
                'data' => $bank->fresh()
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE BANK
    |--------------------------------------------------------------------------
    */

    public function destroy($id): JsonResponse
    {
        try {
            $bank = BankDetail::find($id);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            $bank->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bank deleted successfully'
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}