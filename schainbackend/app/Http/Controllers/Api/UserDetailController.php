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
use Illuminate\Support\Facades\Storage;

class UserDetailController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL USERS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        try {

            $query = UserDetail::with([
                'itemMappings',
                'headEmployeeMappings',
                'cashHeadEmployeeMappings',
                'role'
            ])
            ->orderBy('user_id', 'desc');

            // Filter by type if provided (e.g., ?type=EMPLOYEE or ?type=HEAD)
            if ($request->has('type')) {
                // Fetch the role first to avoid Postgres character varying vs bigint join errors
                $role = \App\Models\Role::where('role', $request->type)->first();
                
                if ($role) {
                    $query->where('role_id', (string) $role->id);
                } else {
                    // If the role doesn't exist, return no users
                    $query->whereRaw('1 = 0');
                }
            }

            $users = $query->get();

            $module = $request->query('module', 'stock'); // Default to stock

            $formattedUsers = $users->map(function ($user) use ($module) {
                $data = [
                    'id' => $user->user_id,
                    'full_name' => $user->name,
                    'type' => $user->role ? $user->role->role : null,
                    'category_name' => $user->category_name,
                    'retailer_user_name' => 'Normal user',
                    'phone_number' => $user->phone_no,
                ];

                if (strtolower($module) === 'cash') {
                    $data['hand_cash'] = sprintf('%0.2f', $user->rak_cash_balance ?? 0);
                    $data['rtgs_cash'] = sprintf('%0.2f', $user->rak_rtgs_balance ?? 0);
                    $data['last_cash_txn_date'] = $user->last_cash_txn_date ? date('Y-m-d H:i:s', strtotime($user->last_cash_txn_date)) : null;
                } else {
                    $data['gm'] = sprintf('%0.3f', $user->grams_grand_total ?? 0);
                    $data['purity'] = sprintf('%0.3f', $user->purity_grand_total ?? 0);
                    $data['last_txn_date'] = $user->last_txn_date ? date('Y-m-d H:i:s', strtotime($user->last_txn_date)) : null;
                }

                $data['profile_image'] = '';
                $data['name'] = $user->name;
                $data['profile_img'] = $user->profile_image ? asset('storage/' . $user->profile_image) : null;
                $data['is_active'] = $user->is_active;

                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $formattedUsers
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE USER + IMAGES + MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [

            /*
            |--------------------------------------------------------------------------
            | USER DETAILS
            |--------------------------------------------------------------------------
            */

            'name' => 'required|string|max:55',

            'user_name' =>
                'required|string|max:55|unique:user_details,user_name',

            'password' =>
                'required|string|min:6',

            'address' =>
                'required|string|max:400',

            'signature' =>
                'required|string|max:45',

            'code' =>
                'required|string|max:255',

            'phone_no' =>
                'required|string|max:15|unique:user_details,phone_no',

            'remarks' =>
                'nullable|string|max:255',

            'proff' =>
                'required|string|max:155',

            'role_id' =>
                'required|integer',

            'customer_commants' =>
                'nullable|string|max:1500',

            'mailing_name' =>
                'required|string|max:255',

            'category_name' =>
                'required|in:GRAMS,PURITY,BOTH',

            'system_id' =>
                'required|string|max:255|unique:user_details,system_id',


            /*
            |--------------------------------------------------------------------------
            | IMAGES
            |--------------------------------------------------------------------------
            */

            'profile_image' =>
                'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

            'aadhar_image' =>
                'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            'is_active' =>
                'sometimes|boolean',

            'is_delete' =>
                'sometimes|boolean',

            'is_billable' =>
                'sometimes|boolean',


            /*
            |--------------------------------------------------------------------------
            | USER SETTINGS
            |--------------------------------------------------------------------------
            */

            'is_customerfitem_cal_enabled' =>
                'sometimes|boolean',

            'is_customerfitem_cal_in_enabled' =>
                'sometimes|boolean',

            'is_create_order_shown' =>
                'sometimes|boolean',

            'is_salary_person' =>
                'sometimes|boolean',

            'is_gold_cal_enabled' =>
                'sometimes|boolean',

            'is_cash_cal_enabled' =>
                'sometimes|boolean',

            'is_wastage_cal_enabled' =>
                'sometimes|boolean',

            'is_otp_verified' =>
                'sometimes|boolean',


            /*
            |--------------------------------------------------------------------------
            | ITEM MAPPINGS
            |--------------------------------------------------------------------------
            */

            'item_mappings' =>
                'nullable|array',

            'item_mappings.*.item_id' =>
                'required|integer',

            'item_mappings.*.item_grams_total' =>
                'nullable|string',

            'item_mappings.*.item_purity_total' =>
                'nullable|string',

            'item_mappings.*.is_primary' =>
                'required|integer',


            /*
            |--------------------------------------------------------------------------
            | HEAD MAPPINGS
            |--------------------------------------------------------------------------
            */

            'head_mappings' =>
                'nullable|array',

            'head_mappings.*.head_id' =>
                'required|integer',


            /*
            |--------------------------------------------------------------------------
            | CASH HEAD MAPPINGS
            |--------------------------------------------------------------------------
            */

            'cash_head_mappings' =>
                'nullable|array',

            'cash_head_mappings.*.head_id' =>
                'required|integer',
        ]);


        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        try {

            $user = DB::transaction(function () use ($request) {

                /*
                |--------------------------------------------------------------------------
                | USER DATA
                |--------------------------------------------------------------------------
                */

                $userData = [

                    'name' =>
                        $request->name,

                    'user_name' =>
                        $request->user_name,

                    'password_hash' =>
                        Hash::make($request->password),

                    'address' =>
                        $request->address,

                    'signature' =>
                        $request->signature,

                    'code' =>
                        $request->code,

                    'phone_no' =>
                        $request->phone_no,

                    'remarks' =>
                        $request->remarks,

                    'proff' =>
                        $request->proff,

                    'role_id' =>
                        $request->role_id,

                    'customer_commants' =>
                        $request->customer_commants,

                    'mailing_name' =>
                        $request->mailing_name,

                    'category_name' =>
                        $request->category_name,

                    'system_id' =>
                        $request->system_id,


                    /*
                    |--------------------------------------------------------------------------
                    | DEFAULT VALUES
                    |--------------------------------------------------------------------------
                    */

                    'is_active' =>
                        $request->input('is_active', true),

                    'is_delete' =>
                        $request->input('is_delete', false),

                    'is_billable' =>
                        $request->input('is_billable', true),

                    'is_customerfitem_cal_enabled' =>
                        $request->input(
                            'is_customerfitem_cal_enabled',
                            false
                        ),

                    'is_customerfitem_cal_in_enabled' =>
                        $request->input(
                            'is_customerfitem_cal_in_enabled',
                            false
                        ),

                    'is_create_order_shown' =>
                        $request->input(
                            'is_create_order_shown',
                            false
                        ),

                    'is_salary_person' =>
                        $request->input(
                            'is_salary_person',
                            false
                        ),

                    'is_gold_cal_enabled' =>
                        $request->input(
                            'is_gold_cal_enabled',
                            true
                        ),

                    'is_cash_cal_enabled' =>
                        $request->input(
                            'is_cash_cal_enabled',
                            true
                        ),

                    'is_wastage_cal_enabled' =>
                        $request->input(
                            'is_wastage_cal_enabled',
                            true
                        ),

                    'is_otp_verified' =>
                        $request->input(
                            'is_otp_verified',
                            false
                        ),

                    'added_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ];


                /*
                |--------------------------------------------------------------------------
                | PROFILE IMAGE
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('profile_image')) {

                    $profilePath = $request
                        ->file('profile_image')
                        ->store('images', 'public');

                    $userData['profile_image'] =
                        $profilePath;
                }


                /*
                |--------------------------------------------------------------------------
                | AADHAR IMAGE
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('aadhar_image')) {

                    $aadharPath = $request
                        ->file('aadhar_image')
                        ->store('images', 'public');

                    $userData['aadhar_image'] =
                        $aadharPath;
                }


                /*
                |--------------------------------------------------------------------------
                | CREATE USER
                |--------------------------------------------------------------------------
                */

                $user = UserDetail::create($userData);


                /*
                |--------------------------------------------------------------------------
                | ITEM MAPPINGS
                |--------------------------------------------------------------------------
                */

                if ($request->filled('item_mappings')) {

                    foreach (
                        $request->input('item_mappings')
                        as $mapping
                    ) {

                        UsersItemsMapping::create([

                            'item_id' =>
                                $mapping['item_id'],

                            'user_id' =>
                                $user->user_id,

                            'item_grams_total' =>
                                $mapping['item_grams_total']
                                ?? '0',

                            'item_purity_total' =>
                                $mapping['item_purity_total']
                                ?? '0',

                            'is_primary' =>
                                $mapping['is_primary'],

                            'added_at' =>
                                now(),
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | HEAD EMPLOYEE MAPPINGS
                |--------------------------------------------------------------------------
                */

                if ($request->filled('head_mappings')) {

                    foreach (
                        $request->input('head_mappings')
                        as $mapping
                    ) {

                        HeadEmployeeMapping::create([

                            'head_id' =>
                                $mapping['head_id'],

                            'employee_id' =>
                                $user->user_id,

                            'added_at' =>
                                now(),
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | CASH HEAD EMPLOYEE MAPPINGS
                |--------------------------------------------------------------------------
                */

                if ($request->filled('cash_head_mappings')) {

                    foreach (
                        $request->input('cash_head_mappings')
                        as $mapping
                    ) {

                        CashHeadEmployeeMapping::create([

                            'head_id' =>
                                $mapping['head_id'],

                            'employee_id' =>
                                $user->user_id,

                            'added_at' =>
                                now(),
                        ]);
                    }
                }


                return $user;
            });


            $user->refresh();

            return response()->json([

                'success' => true,

                'message' =>
                    'User and mappings created successfully',

                'data' => [

                    'user' =>
                        $user,

                    'profile_image_url' =>
                        $user->profile_image
                        ? asset(
                            'storage/' .
                            $user->profile_image
                        )
                        : null,

                    'aadhar_image_url' =>
                        $user->aadhar_image
                        ? asset(
                            'storage/' .
                            $user->aadhar_image
                        )
                        : null,
                ]

            ], 201);

        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to create user and mappings',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET USER BY ID
    |--------------------------------------------------------------------------
    */

    public function show($id): JsonResponse
    {
        try {

            $user = UserDetail::with([
                'itemMappings',
                'headEmployeeMappings',
                'cashHeadEmployeeMappings',
                'role'
            ])
                ->where('user_id', $id)
                ->first();

            if (!$user) {

                return response()->json([

                    'success' => false,

                    'message' =>
                        'User not found'

                ], 404);
            }


            $userArray = $user->toArray();
            $userArray['gm'] = sprintf('%0.3f', $user->grams_grand_total ?? 0);
            $userArray['purity'] = sprintf('%0.3f', $user->purity_grand_total ?? 0);
            $userArray['id'] = $user->user_id;
            $userArray['full_name'] = $user->name;
            $userArray['phone_number'] = $user->phone_no;
            $userArray['profile_img'] = $user->profile_image ? asset('storage/' . $user->profile_image) : null;
            $userArray['type'] = $user->role ? $user->role->role : null;

            return response()->json([

                'success' => true,

                'message' =>
                    'User retrieved successfully',

                'data' => [

                    'user' =>
                        $userArray,

                    'profile_image_url' =>
                        $user->profile_image
                        ? asset(
                            'storage/' .
                            $user->profile_image
                        )
                        : null,

                    'aadhar_image_url' =>
                        $user->aadhar_image
                        ? asset(
                            'storage/' .
                            $user->aadhar_image
                        )
                        : null,
                ]

            ], 200);

        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to retrieve user',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE USER + IMAGES
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        $id
    ): JsonResponse {

        try {

            /*
            |--------------------------------------------------------------------------
            | FIND USER
            |--------------------------------------------------------------------------
            */

            $user = UserDetail::where(
                'user_id',
                $id
            )->first();


            if (!$user) {

                return response()->json([

                    'success' => false,

                    'message' =>
                        'User not found'

                ], 404);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $validator = Validator::make(
                $request->all(),
                [

                    'name' =>
                        'sometimes|string|max:55',

                    'user_name' =>
                        'sometimes|string|max:55|unique:user_details,user_name,' .
                        $id .
                        ',user_id',

                    'password' =>
                        'nullable|string|min:6',

                    'address' =>
                        'sometimes|string|max:400',

                    'signature' =>
                        'sometimes|string|max:45',

                    'code' =>
                        'sometimes|string|max:255',

                    'phone_no' =>
                        'sometimes|string|max:15|unique:user_details,phone_no,' .
                        $id .
                        ',user_id',

                    'remarks' =>
                        'nullable|string|max:255',

                    'proff' =>
                        'sometimes|string|max:155',

                    'role_id' =>
                        'sometimes|integer',

                    'customer_commants' =>
                        'nullable|string|max:1500',

                    'mailing_name' =>
                        'sometimes|string|max:255',

                    'category_name' =>
                        'sometimes|in:GRAMS,PURITY,BOTH',

                    'system_id' =>
                        'sometimes|string|max:255|unique:user_details,system_id,' .
                        $id .
                        ',user_id',


                    /*
                    |--------------------------------------------------------------------------
                    | IMAGES
                    |--------------------------------------------------------------------------
                    */

                    'profile_image' =>
                        'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',

                    'aadhar_image' =>
                        'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',


                    /*
                    |--------------------------------------------------------------------------
                    | BOOLEAN FIELDS
                    |--------------------------------------------------------------------------
                    */

                    'is_active' =>
                        'sometimes|boolean',

                    'is_delete' =>
                        'sometimes|boolean',

                    'is_billable' =>
                        'sometimes|boolean',

                    'is_customerfitem_cal_enabled' =>
                        'sometimes|boolean',

                    'is_customerfitem_cal_in_enabled' =>
                        'sometimes|boolean',

                    'is_create_order_shown' =>
                        'sometimes|boolean',

                    'is_salary_person' =>
                        'sometimes|boolean',

                    'is_gold_cal_enabled' =>
                        'sometimes|boolean',

                    'is_cash_cal_enabled' =>
                        'sometimes|boolean',

                    'is_wastage_cal_enabled' =>
                        'sometimes|boolean',

                    'is_otp_verified' =>
                        'sometimes|boolean',
                ]
            );


            if ($validator->fails()) {

                return response()->json([

                    'success' => false,

                    'message' =>
                        'Validation failed',

                    'errors' =>
                        $validator->errors()

                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE INSIDE TRANSACTION
            |--------------------------------------------------------------------------
            */

            DB::transaction(function () use ($request, $user) {

                /*
                |--------------------------------------------------------------------------
                | ONLY UPDATE SENT FIELDS
                |--------------------------------------------------------------------------
                */

                $userData = [];


                $fields = [

                    'name',
                    'user_name',
                    'address',
                    'signature',
                    'code',
                    'phone_no',
                    'remarks',
                    'proff',
                    'role_id',
                    'customer_commants',
                    'mailing_name',
                    'category_name',
                    'system_id',

                    'is_active',
                    'is_delete',
                    'is_billable',

                    'is_customerfitem_cal_enabled',
                    'is_customerfitem_cal_in_enabled',
                    'is_create_order_shown',
                    'is_salary_person',
                    'is_gold_cal_enabled',
                    'is_cash_cal_enabled',
                    'is_wastage_cal_enabled',
                    'is_otp_verified',
                ];


                foreach ($fields as $field) {

                    if ($request->has($field)) {

                        if (
                            in_array(
                                $field,
                                [
                                    'is_active',
                                    'is_delete',
                                    'is_billable',
                                    'is_customerfitem_cal_enabled',
                                    'is_customerfitem_cal_in_enabled',
                                    'is_create_order_shown',
                                    'is_salary_person',
                                    'is_gold_cal_enabled',
                                    'is_cash_cal_enabled',
                                    'is_wastage_cal_enabled',
                                    'is_otp_verified'
                                ]
                            )
                        ) {

                            $userData[$field] =
                                $request->boolean($field);

                        } else {

                            $userData[$field] =
                                $request->input($field);
                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | PASSWORD UPDATE
                |--------------------------------------------------------------------------
                */

                if ($request->filled('password')) {

                    $userData['password_hash'] =
                        Hash::make(
                            $request->password
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | PROFILE IMAGE UPDATE
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('profile_image')) {

                    /*
                    | Delete old profile image
                    */

                    if (
                        $user->profile_image &&
                        Storage::disk('public')
                            ->exists($user->profile_image)
                    ) {

                        Storage::disk('public')
                            ->delete(
                                $user->profile_image
                            );
                    }


                    /*
                    | Store new profile image
                    */

                    $profilePath =
                        $request
                            ->file('profile_image')
                            ->store(
                                'images',
                                'public'
                            );


                    $userData['profile_image'] =
                        $profilePath;
                }


                /*
                |--------------------------------------------------------------------------
                | AADHAR IMAGE UPDATE
                |--------------------------------------------------------------------------
                */

                if ($request->hasFile('aadhar_image')) {

                    /*
                    | Delete old Aadhar image
                    */

                    if (
                        $user->aadhar_image &&
                        Storage::disk('public')
                            ->exists($user->aadhar_image)
                    ) {

                        Storage::disk('public')
                            ->delete(
                                $user->aadhar_image
                            );
                    }


                    /*
                    | Store new Aadhar image
                    */

                    $aadharPath =
                        $request
                            ->file('aadhar_image')
                            ->store(
                                'images',
                                'public'
                            );


                    $userData['aadhar_image'] =
                        $aadharPath;
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE TIME
                |--------------------------------------------------------------------------
                */

                $userData['updated_at'] =
                    now();


                /*
                |--------------------------------------------------------------------------
                | SAVE USER
                |--------------------------------------------------------------------------
                */

                $user->update($userData);
            });


            /*
            |--------------------------------------------------------------------------
            | REFRESH DATA
            |--------------------------------------------------------------------------
            */

            $user->refresh();


            /*
            |--------------------------------------------------------------------------
            | SUCCESS RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'User updated successfully',

                'data' => [

                    'user' =>
                        $user,

                    'profile_image_url' =>
                        $user->profile_image
                        ? asset(
                            'storage/' .
                            $user->profile_image
                        )
                        : null,

                    'aadhar_image_url' =>
                        $user->aadhar_image
                        ? asset(
                            'storage/' .
                            $user->aadhar_image
                        )
                        : null,
                ]

            ], 200);


        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to update user',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE USER + MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function destroy($id): JsonResponse
    {
        try {

            DB::transaction(function () use ($id) {

                /*
                |--------------------------------------------------------------------------
                | FIND USER
                |--------------------------------------------------------------------------
                */

                $user = UserDetail::where(
                    'user_id',
                    $id
                )->first();


                if (!$user) {

                    abort(
                        404,
                        'User not found'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | DELETE PROFILE IMAGE
                |--------------------------------------------------------------------------
                */

                if (
                    $user->profile_image &&
                    Storage::disk('public')
                        ->exists(
                            $user->profile_image
                        )
                ) {

                    Storage::disk('public')
                        ->delete(
                            $user->profile_image
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | DELETE AADHAR IMAGE
                |--------------------------------------------------------------------------
                */

                if (
                    $user->aadhar_image &&
                    Storage::disk('public')
                        ->exists(
                            $user->aadhar_image
                        )
                ) {

                    Storage::disk('public')
                        ->delete(
                            $user->aadhar_image
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | DELETE ITEM MAPPINGS
                |--------------------------------------------------------------------------
                */

                UsersItemsMapping::where(
                    'user_id',
                    $user->user_id
                )->delete();


                /*
                |--------------------------------------------------------------------------
                | DELETE HEAD MAPPINGS
                |--------------------------------------------------------------------------
                */

                HeadEmployeeMapping::where(
                    'employee_id',
                    $user->user_id
                )->delete();


                /*
                |--------------------------------------------------------------------------
                | DELETE CASH HEAD MAPPINGS
                |--------------------------------------------------------------------------
                */

                CashHeadEmployeeMapping::where(
                    'employee_id',
                    $user->user_id
                )->delete();


                /*
                |--------------------------------------------------------------------------
                | DELETE USER
                |--------------------------------------------------------------------------
                */

                $user->delete();
            });


            return response()->json([

                'success' => true,

                'message' =>
                    'User and related mappings deleted successfully'

            ], 200);


        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to delete user',

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }
}