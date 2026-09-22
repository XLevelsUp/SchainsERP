<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SystemSettingController extends Controller
{
    /**
     * Display a listing of all system settings.
     */
    public function index(): JsonResponse
    {
        try {
            $settings = SystemSetting::all();
            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'System settings retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('SystemSettingController@index failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system settings',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Store a newly created system setting.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'setting_key' => 'required|string|unique:system_settings,setting_key|max:255',
                'setting_value' => 'required|array',
                'description' => 'nullable|string',
            ]);

            $setting = SystemSetting::create($validated);

            return response()->json([
                'success' => true,
                'data' => $setting,
                'message' => 'System setting created successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('SystemSettingController@store failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create system setting',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Display the specified system setting by key.
     */
    public function show(string $key): JsonResponse
    {
        try {
            $setting = SystemSetting::where('setting_key', $key)->firstOrFail();
            return response()->json([
                'success' => true,
                'data' => $setting,
                'message' => 'System setting retrieved successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'System setting not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SystemSettingController@show failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system setting',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Update the specified system setting.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        try {
            $setting = SystemSetting::where('setting_key', $key)->firstOrFail();

            $validated = $request->validate([
                'setting_value' => 'sometimes|required|array',
                'description' => 'nullable|string',
            ]);

            $setting->update($validated);

            return response()->json([
                'success' => true,
                'data' => $setting,
                'message' => 'System setting updated successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'System setting not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('SystemSettingController@update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update system setting',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Remove the specified system setting.
     */
    public function destroy(string $key): JsonResponse
    {
        try {
            $setting = SystemSetting::where('setting_key', $key)->firstOrFail();
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'System setting deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'System setting not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('SystemSettingController@destroy failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete system setting',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}
