<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'User Details',
    description: 'User Details CRUD API'
)]
class UserDetailController extends Controller
{
    /**
     * Get all user details
     */
    #[OA\Get(
        path: '/api/user-details',
        operationId: 'getAllUserDetails',
        tags: ['User Details'],
        summary: 'Get all user details',
        description: 'Returns all user details',
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details retrieved successfully'
            )
        ]
    )]
    public function index(): JsonResponse
    {
        $users = UserDetail::orderBy('user_id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'User details retrieved successfully',
            'data' => $users
        ], 200);
    }


    /**
     * Create user detail
     */
    #[OA\Post(
        path: '/api/user-details',
        operationId: 'createUserDetail',
        tags: ['User Details'],
        summary: 'Create user detail',
        description: 'Create a new user detail with Aadhar image',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [
                        'name',
                        'user_name',
                        'password_hash',
                        'address',
                        'signature',
                        'code',
                        'phone_no',
                        'proff',
                        'role_id',
                        'mailing_name',
                        'category_name',
                        'system_id',
                        'is_customerfitem_cal_enabled',
                        'is_customerfitem_cal_in_enabled'
                    ],
                    properties: [

                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            example: 'Mohamed Azar'
                        ),

                        new OA\Property(
                            property: 'user_name',
                            type: 'string',
                            example: 'azar'
                        ),

                        new OA\Property(
                            property: 'password_hash',
                            type: 'string',
                            example: '123456'
                        ),

                        new OA\Property(
                            property: 'address',
                            type: 'string',
                            example: 'Chennai'
                        ),

                        new OA\Property(
                            property: 'signature',
                            type: 'string',
                            example: 'AZ'
                        ),

                        new OA\Property(
                            property: 'code',
                            type: 'string',
                            example: 'EMP001'
                        ),

                        new OA\Property(
                            property: 'phone_no',
                            type: 'string',
                            example: '9876543210'
                        ),

                        new OA\Property(
                            property: 'remarks',
                            type: 'string',
                            nullable: true,
                            example: 'Test user'
                        ),

                        new OA\Property(
                            property: 'proff',
                            type: 'string',
                            example: 'Employee'
                        ),

                        new OA\Property(
                            property: 'role_id',
                            type: 'string',
                            example: 'Admin'
                        ),

                        new OA\Property(
                            property: 'customer_commants',
                            type: 'string',
                            nullable: true,
                            example: 'Customer comments'
                        ),

                        new OA\Property(
                            property: 'mailing_name',
                            type: 'string',
                            example: 'Mohamed Azar'
                        ),

                        new OA\Property(
                            property: 'image_url',
                            type: 'string',
                            nullable: true,
                            example: 'profile/image.jpg'
                        ),

                        new OA\Property(
                            property: 'profile_image',
                            type: 'string',
                            format: 'binary',
                            nullable: true
                        ),

                        new OA\Property(
                            property: 'aadhar_image',
                            type: 'string',
                            format: 'binary',
                            nullable: true,
                            description: 'Aadhar front and back images can be uploaded'
                        ),

                        new OA\Property(
                            property: 'category_name',
                            type: 'string',
                            enum: ['GRAMS', 'PURITY', 'BOTH'],
                            example: 'GRAMS'
                        ),

                        new OA\Property(
                            property: 'system_id',
                            type: 'string',
                            example: 'SYS001'
                        ),

                        new OA\Property(
                            property: 'is_active',
                            type: 'boolean',
                            example: true
                        ),

                        new OA\Property(
                            property: 'is_delete',
                            type: 'boolean',
                            example: false
                        ),

                        new OA\Property(
                            property: 'is_billable',
                            type: 'boolean',
                            example: false
                        ),

                        new OA\Property(
                            property: 'is_create_order_shown',
                            type: 'boolean',
                            example: false
                        ),

                        new OA\Property(
                            property: 'is_salary_person',
                            type: 'boolean',
                            example: false
                        ),

                        new OA\Property(
                            property: 'is_gold_cal_enabled',
                            type: 'boolean',
                            example: true
                        ),

                        new OA\Property(
                            property: 'is_cash_cal_enabled',
                            type: 'boolean',
                            example: true
                        ),

                        new OA\Property(
                            property: 'is_wastage_cal_enabled',
                            type: 'boolean',
                            example: true
                        ),

                        new OA\Property(
                            property: 'is_customerfitem_cal_enabled',
                            type: 'boolean',
                            example: true
                        ),

                        new OA\Property(
                            property: 'is_customerfitem_cal_in_enabled',
                            type: 'boolean',
                            example: true
                        ),

                        new OA\Property(
                            property: 'is_otp_verified',
                            type: 'boolean',
                            example: false
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User detail created successfully'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]


        
    )]
    
    public function store(Request $request): JsonResponse
    {
         // 1. Convert Swagger multipart/form-data values
    //    from "0"/"1" strings to boolean true/false

    $booleanFields = [
        'is_active',
        'is_delete',
        'is_billable',
        'is_create_order_shown',
        'is_salary_person',
        'is_gold_cal_enabled',
        'is_cash_cal_enabled',
        'is_wastage_cal_enabled',
        'is_customerfitem_cal_enabled',
        'is_customerfitem_cal_in_enabled',
        'is_otp_verified',
    ];

    foreach ($booleanFields as $field) {
        if ($request->has($field)) {
            $request->merge([
                $field => filter_var(
                    $request->input($field),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                )
            ]);
        }
    }

        $validated = $request->validate([

            'name' => 'required|string|max:55',
            'user_name' => 'required|string|max:55|unique:user_details,user_name',
            'password_hash' => 'required|string|min:6',

            'address' => 'required|string|max:400',
            'signature' => 'required|string|max:45',
            'code' => 'required|string|max:255',

            'phone_no' => 'required|string|max:15|unique:user_details,phone_no',

            'remarks' => 'nullable|string|max:255',
            'proff' => 'required|string|max:155',
            'role_id' => 'required|string|max:50',

            'customer_commants' => 'nullable|string|max:1500',
            'mailing_name' => 'required|string|max:255',

            'image_url' => 'nullable|string|max:255',

            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'aadhar_image' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',

            'category_name' => 'required|in:GRAMS,PURITY,BOTH',

            'system_id' => 'required|string|max:255|unique:user_details,system_id',

            'is_active' => 'sometimes|boolean',
            'is_delete' => 'sometimes|boolean',
            'is_billable' => 'sometimes|boolean',
            'is_create_order_shown' => 'sometimes|boolean',

            'is_salary_person' => 'sometimes|boolean',

            'is_gold_cal_enabled' => 'sometimes|boolean',
            'is_cash_cal_enabled' => 'sometimes|boolean',
            'is_wastage_cal_enabled' => 'sometimes|boolean',

            'is_customerfitem_cal_enabled' => 'required|boolean',
            'is_customerfitem_cal_in_enabled' => 'required|boolean',

            'is_otp_verified' => 'sometimes|boolean',

            'grams_grand_total' => 'sometimes|numeric',
            'purity_grand_total' => 'sometimes|numeric',

            'per_day_salary' => 'sometimes|numeric',
            'rak_cash_balance' => 'sometimes|numeric',
            'rak_rtgs_balance' => 'sometimes|numeric',
        ]);

        // Password hashing
        $validated['password_hash'] = Hash::make(
            $validated['password_hash']
        );

       // Profile image
if ($request->hasFile('profile_image')) {

    $path = $request
        ->file('profile_image')
        ->store('images', 'public');

    $validated['profile_image'] = $path;
}

// Aadhar image
if ($request->hasFile('aadhar_image')) {

    $path = $request
        ->file('aadhar_image')
        ->store('images', 'public');

    $validated['aadhar_image'] = $path;
}

        $user = UserDetail::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User detail created successfully',
            'data' => $user
        ], 201);
    }


    /**
     * Get single user
     */
    #[OA\Get(
        path: '/api/user-details/{id}',
        operationId: 'getUserDetailById',
        tags: ['User Details'],
        summary: 'Get user detail by ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User detail retrieved successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'User detail not found'
            )
        ]
    )]
    public function show($id): JsonResponse
    {
        $user = UserDetail::find($id);

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'User detail not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User detail retrieved successfully',
            'data' => $user
        ], 200);
    }


    /**
     * Update user
     */
    #[OA\Post(
        path: '/api/user-details/{id}',
        operationId: 'updateUserDetail',
        tags: ['User Details'],
        summary: 'Update user detail',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [

                        new OA\Property(
                            property: 'name',
                            type: 'string',
                            example: 'Updated User'
                        ),

                        new OA\Property(
                            property: 'phone_no',
                            type: 'string',
                            example: '9876543210'
                        ),

                        new OA\Property(
                            property: 'role_id',
                            type: 'string',
                            example: 'Admin'
                        ),

                        new OA\Property(
                            property: 'profile_image',
                            type: 'string',
                            format: 'binary'
                        ),

                        new OA\Property(
                            property: 'aadhar_image',
                            type: 'string',
                            format: 'binary'
                        ),

                        new OA\Property(
                            property: 'is_active',
                            type: 'boolean',
                            example: true
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User detail updated successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'User detail not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
    public function update(Request $request, $id): JsonResponse
    {
        $user = UserDetail::find($id);

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'User detail not found'
            ], 404);
        }

        $validated = $request->validate([

            'name' => 'sometimes|string|max:55',

            'user_name' =>
                'sometimes|string|max:55|unique:user_details,user_name,' .
                $id . ',user_id',

            'password_hash' => 'sometimes|string|min:6',

            'address' => 'sometimes|string|max:400',
            'signature' => 'sometimes|string|max:45',
            'code' => 'sometimes|string|max:255',

            'phone_no' =>
                'sometimes|string|max:15|unique:user_details,phone_no,' .
                $id . ',user_id',

            'remarks' => 'nullable|string|max:255',
            'proff' => 'sometimes|string|max:155',
            'role_id' => 'sometimes|string|max:50',

            'customer_commants' => 'nullable|string|max:1500',
            'mailing_name' => 'sometimes|string|max:255',

            'image_url' => 'nullable|string|max:255',

            'profile_image' =>
                'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'aadhar_image' =>
                'nullable|image|mimes:jpg,jpeg,png|max:4096',

            'category_name' =>
                'sometimes|in:GRAMS,PURITY,BOTH',

            'system_id' =>
                'sometimes|string|max:255|unique:user_details,system_id,' .
                $id . ',user_id',

            'is_active' => 'sometimes|boolean',
            'is_delete' => 'sometimes|boolean',
            'is_billable' => 'sometimes|boolean',
            'is_create_order_shown' => 'sometimes|boolean',

            'is_salary_person' => 'sometimes|boolean',

            'is_gold_cal_enabled' => 'sometimes|boolean',
            'is_cash_cal_enabled' => 'sometimes|boolean',
            'is_wastage_cal_enabled' => 'sometimes|boolean',

            'is_customerfitem_cal_enabled' => 'sometimes|boolean',
            'is_customerfitem_cal_in_enabled' => 'sometimes|boolean',

            'is_otp_verified' => 'sometimes|boolean'
        ]);

        if (
            $request->filled('password_hash')
        ) {
            $validated['password_hash'] = Hash::make(
                $validated['password_hash']
            );
        }

        if ($request->hasFile('profile_image')) {

            if ($user->profile_image) {
                Storage::disk('public')
                    ->delete($user->profile_image);
            }

            $validated['profile_image'] =
                $request->file('profile_image')
                    ->store('profile_images', 'public');
        }

        if ($request->hasFile('aadhar_image')) {

            if ($user->aadhar_image) {
                Storage::disk('public')
                    ->delete($user->aadhar_image);
            }

            $validated['aadhar_image'] =
                $request->file('aadhar_image')
                    ->store('aadhar_images', 'public');
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User detail updated successfully',
            'data' => $user->fresh()
        ], 200);
    }


    /**
     * Delete user
     */
    #[OA\Delete(
        path: '/api/user-details/{id}',
        operationId: 'deleteUserDetail',
        tags: ['User Details'],
        summary: 'Delete user detail',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User detail deleted successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'User detail not found'
            )
        ]
    )]
    public function destroy($id): JsonResponse
    {
        $user = UserDetail::find($id);

        if (!$user) {

            return response()->json([
                'success' => false,
                'message' => 'User detail not found'
            ], 404);
        }

        if ($user->profile_image) {
            Storage::disk('public')
                ->delete($user->profile_image);
        }

        if ($user->aadhar_image) {
            Storage::disk('public')
                ->delete($user->aadhar_image);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User detail deleted successfully'
        ], 200);
    }
}