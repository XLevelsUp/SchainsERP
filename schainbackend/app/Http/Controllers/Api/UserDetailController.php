<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use App\Models\UsersItemsMapping;
use App\Models\HeadEmployeeMapping;
use App\Models\CashHeadEmployeeMapping;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserDetailController extends Controller
{
    /**
     * GET ALL USERS
     */
    public function index(): JsonResponse
    {
        $users = UserDetail::orderBy('user_id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ], 200);
    }


    /**
     * CREATE USER + MAPPINGS
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [

            // User details
            'name' => 'required|string|max:55',
            'user_name' => 'required|string|max:55|unique:user_details,user_name',
            'password' => 'required|string|min:6',

            'address' => 'required|string|max:400',
            'signature' => 'required|string|max:45',
            'code' => 'required|string|max:255',
            'phone_no' => 'required|string|max:15|unique:user_details,phone_no',
            'remarks' => 'nullable|string|max:255',
            'proff' => 'required|string|max:155',

            'role_id' => 'required|string|max:50',
            'system_id' => 'required|string|max:255|unique:user_details,system_id',

            'mailing_name' => 'required|string|max:255',
            'customer_commants' => 'nullable|string|max:1500',

            'category_name' => 'required|in:GRAMS,PURITY,BOTH',

            'is_active' => 'sometimes|boolean',
            'is_delete' => 'sometimes|boolean',
            'is_billable' => 'sometimes|boolean',

            // Mappings
            'item_mappings' => 'nullable|array',
            'item_mappings.*.item_id' => 'required|integer',
            'item_mappings.*.item_grams_total' => 'nullable|string',
            'item_mappings.*.item_purity_total' => 'nullable|string',
            'item_mappings.*.is_primary' => 'required|integer',

            'head_mappings' => 'nullable|array',
            'head_mappings.*.head_id' => 'required|integer',

            'cash_head_mappings' => 'nullable|array',
            'cash_head_mappings.*.head_id' => 'required|integer',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $result = DB::transaction(function () use ($request) {

                /*
                |--------------------------------------------------------------------------
                | 1. CREATE USER
                |--------------------------------------------------------------------------
                */

                $user = UserDetail::create([

                    'name' => $request->name,
                    'user_name' => $request->user_name,

                    // Password is securely hashed
                    'password_hash' => Hash::make($request->password),

                    'address' => $request->address,
                    'signature' => $request->signature,
                    'code' => $request->code,
                    'phone_no' => $request->phone_no,
                    'remarks' => $request->remarks,
                    'proff' => $request->proff,

                    'role_id' => $request->role_id,

                    'customer_commants' => $request->customer_commants,
                    'mailing_name' => $request->mailing_name,

                    'category_name' => $request->category_name,
                    'system_id' => $request->system_id,

                    'is_active' => $request->input('is_active', true),
                    'is_delete' => $request->input('is_delete', false),
                    'is_billable' => $request->input('is_billable', false),

                    'added_at' => now(),
                    'updated_at' => now(),
                ]);


                /*
                |--------------------------------------------------------------------------
                | 2. USERS ITEMS MAPPINGS
                |--------------------------------------------------------------------------
                */

                if ($request->has('item_mappings')) {

                    foreach ($request->item_mappings as $mapping) {

                        UsersItemsMapping::create([

                            'item_id' => $mapping['item_id'],

                            // Newly created user ID
                            'user_id' => $user->user_id,

                            'item_grams_total' =>
                                $mapping['item_grams_total'] ?? '0',

                            'item_purity_total' =>
                                $mapping['item_purity_total'] ?? '0',

                            'is_primary' =>
                                $mapping['is_primary'],

                            'added_at' => now(),
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 3. HEAD EMPLOYEE MAPPINGS
                |--------------------------------------------------------------------------
                */

                if ($request->has('head_mappings')) {

                    foreach ($request->head_mappings as $mapping) {

                        HeadEmployeeMapping::create([

                            'head_id' =>
                                $mapping['head_id'],

                            // Newly created user becomes employee
                            'employee_id' =>
                                $user->user_id,

                            'added_at' => now(),
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 4. CASH HEAD EMPLOYEE MAPPINGS
                |--------------------------------------------------------------------------
                */

                if ($request->has('cash_head_mappings')) {

                    foreach ($request->cash_head_mappings as $mapping) {

                        CashHeadEmployeeMapping::create([

                            'head_id' =>
                                $mapping['head_id'],

                            // Newly created user becomes employee
                            'employee_id' =>
                                $user->user_id,

                            'added_at' => now(),
                        ]);
                    }
                }


                return $user;
            });


            return response()->json([
                'success' => true,
                'message' => 'User and mappings created successfully',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user and mappings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * GET USER BY ID
     */
    public function show($id): JsonResponse
    {
        $user = UserDetail::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ], 200);
    }


    /**
     * DELETE USER
     */
    public function destroy($id): JsonResponse
    {
        try {

            DB::transaction(function () use ($id) {

                $user = UserDetail::find($id);

                if (!$user) {
                    abort(404, 'User not found');
                }

                // Delete related mappings first
                UsersItemsMapping::where(
                    'user_id',
                    $user->user_id
                )->delete();

                HeadEmployeeMapping::where(
                    'employee_id',
                    $user->user_id
                )->delete();

                CashHeadEmployeeMapping::where(
                    'employee_id',
                    $user->user_id
                )->delete();

                // Delete user
                $user->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'User and related mappings deleted successfully'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }
}