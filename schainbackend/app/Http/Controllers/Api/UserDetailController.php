<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'User Details',
    description: 'User Details CRUD API'
)]
class UserDetailController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL USER DETAILS
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/user-details',
        operationId: 'getUserDetails',
        tags: ['User Details'],
        summary: 'Get all user details',
        description: 'Returns all user details from the database',
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


    /*
    |--------------------------------------------------------------------------
    | CREATE USER DETAIL
    |--------------------------------------------------------------------------
    */

    #[OA\Post(
        path: '/api/user-details',
        operationId: 'createUserDetail',
        tags: ['User Details'],
        summary: 'Create a new user detail',
        description: 'Creates a new user detail record',

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'name',
                    'user_name',
                    'password_hash',
                    'address',
                    'signature',
                    'code',
                    'phone_no',
                    'proff',
                    'role',
                    'mailing_name',
                    'category_name',
                    'system_id'
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
                        example: 'Testing'
                    ),

                    new OA\Property(
                        property: 'proff',
                        type: 'string',
                        example: 'Developer'
                    ),

                    new OA\Property(
                        property: 'role',
                        type: 'string',
                        example: 'Admin'
                    ),

                    new OA\Property(
                        property: 'customer_commants',
                        type: 'string',
                        nullable: true,
                        example: 'Customer related comments'
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
                        example: 'https://example.com/image.jpg'
                    ),

                    new OA\Property(
                        property: 'profile_image',
                        type: 'string',
                        nullable: true,
                        example: 'profile.jpg'
                    ),

                    new OA\Property(
                        property: 'category_name',
                        type: 'string',
                        enum: ['GRAMS', 'PURITY', 'BOTH'],
                        example: 'BOTH'
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
                        property: 'grams_grand_total',
                        type: 'number',
                        format: 'double',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'purity_grand_total',
                        type: 'number',
                        format: 'double',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'stone_weight_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'beads_weight_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'net_weight_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'gross_weight_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'beads_stone_weight_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'stone_cost_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'beads_cost_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'beads_stone_cost_grand_total',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: 0
                    ),

                    new OA\Property(
                        property: 'order_grand_total',
                        type: 'number',
                        format: 'double',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'order_grand_no_of_pcs',
                        type: 'integer',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'last_txn_date',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'is_salary_person',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'per_day_salary',
                        type: 'number',
                        format: 'double',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'rak_cash_balance',
                        type: 'number',
                        format: 'double',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'last_cash_txn_date',
                        type: 'string',
                        nullable: true,
                        example: null
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
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_customerfitem_cal_in_enabled',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'report_password',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'otp',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'is_otp_verified',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'allot_value',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'is_remainder_shown',
                        type: 'boolean',
                        nullable: true,
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_delivery_item_shown',
                        type: 'boolean',
                        nullable: true,
                        example: false
                    ),

                    new OA\Property(
                        property: 'rak_rtgs_balance',
                        type: 'number',
                        format: 'double',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'last_rtgs_txn_date',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'is_polish_needed',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_wa_delivery_stock_needed',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_polish_chk_need_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_delivery_chk_need_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_cashamt_thermal_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_customer_touch_need_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_complete_history_need_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_create_order_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_cash_mngmt_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_freeze_entry_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_admin_login_otp_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_customer_cmts_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_outside_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_tally_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_die_num_search_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_con_box_rpt_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_box_tot_rpt_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_ob_cb_rpt_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_gallery_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_worker_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_emp_group_task_need_to_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_day_grand_rpt_need_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_need_pink_box_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_need_order_status_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_need_role_wise_cash_rpt_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'need_roles_in_rpt_shown',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'is_need_to_retailer_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_need_grosswgt_print_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_cus_fitem_pur_out_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_cus_fitem_pur_in_shown',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_need_show_order_display_in_head_login',
                        type: 'boolean',
                        nullable: true,
                        example: false
                    ),

                    new OA\Property(
                        property: 'attendance_user_id',
                        type: 'integer',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'is_metal_stock_shown',
                        type: 'boolean',
                        nullable: true,
                        example: false
                    ),

                    new OA\Property(
                        property: 'top_print_out',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'incentive_sent_to',
                        type: 'integer',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'vendor_barcode_group_id',
                        type: 'integer',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'vendor_short_name',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'credit_out_limit',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'credit_in_limit',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'credit_limit_remarks',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'credit_limit_updated_at',
                        type: 'string',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'credit_limit_updated_by',
                        type: 'integer',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'temp_gram_credit_limit',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'temp_purity_credit_limit',
                        type: 'number',
                        format: 'double',
                        nullable: true,
                        example: null
                    ),

                    new OA\Property(
                        property: 'default_mc_choice',
                        type: 'string',
                        enum: ['RETAILER', 'WHOLESALER'],
                        nullable: true,
                        example: 'RETAILER'
                    ),

                    new OA\Property(
                        property: 'page_access',
                        type: 'string',
                        nullable: true,
                        example: '{"dashboard":true,"users":true}'
                    ),

                    new OA\Property(
                        property: 'is_admin_head',
                        type: 'integer',
                        example: 0
                    ),

                    new OA\Property(
                        property: 'main_head_id',
                        type: 'integer',
                        nullable: true,
                        example: null
                    )
                ]
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
        $validated = $request->validate([

            // Required fields
            'name' => 'required|string|max:55',

            'user_name' =>
                'required|string|max:55|unique:user_details,user_name',

            'password_hash' =>
                'required|string|max:255',

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

            'role' =>
                'required|string|max:50',

            'customer_commants' =>
                'nullable|string|max:1500',

            'mailing_name' =>
                'required|string|max:255',

            'image_url' =>
                'nullable|string|max:255',

            'profile_image' =>
                'nullable|string',

            'category_name' =>
                'required|in:GRAMS,PURITY,BOTH',

            'system_id' =>
                'required|string|max:255|unique:user_details,system_id',

            // Boolean fields
            'is_active' => 'nullable|boolean',
            'is_delete' => 'nullable|boolean',
            'is_billable' => 'nullable|boolean',
            'is_create_order_shown' => 'nullable|boolean',

            'is_salary_person' => 'nullable|boolean',

            'is_gold_cal_enabled' => 'nullable|boolean',
            'is_cash_cal_enabled' => 'nullable|boolean',
            'is_wastage_cal_enabled' => 'nullable|boolean',

            'is_customerfitem_cal_enabled' => 'nullable|boolean',
            'is_customerfitem_cal_in_enabled' => 'nullable|boolean',

            'is_otp_verified' => 'nullable|boolean',

            'is_remainder_shown' => 'nullable|boolean',
            'is_delivery_item_shown' => 'nullable|boolean',

            'is_polish_needed' => 'nullable|boolean',
            'is_wa_delivery_stock_needed' => 'nullable|boolean',
            'is_polish_chk_need_shown' => 'nullable|boolean',
            'is_delivery_chk_need_shown' => 'nullable|boolean',

            'is_cashamt_thermal_shown' => 'nullable|boolean',
            'is_customer_touch_need_shown' => 'nullable|boolean',
            'is_complete_history_need_shown' => 'nullable|boolean',
            'is_create_order_need_to_shown' => 'nullable|boolean',
            'is_cash_mngmt_need_to_shown' => 'nullable|boolean',
            'is_freeze_entry_need_to_shown' => 'nullable|boolean',
            'is_admin_login_otp_need_to_shown' => 'nullable|boolean',
            'is_customer_cmts_need_to_shown' => 'nullable|boolean',
            'is_outside_need_to_shown' => 'nullable|boolean',
            'is_tally_need_to_shown' => 'nullable|boolean',
            'is_die_num_search_need_to_shown' => 'nullable|boolean',

            'is_con_box_rpt_need_to_shown' => 'nullable|boolean',
            'is_box_tot_rpt_need_to_shown' => 'nullable|boolean',
            'is_ob_cb_rpt_need_to_shown' => 'nullable|boolean',
            'is_gallery_need_to_shown' => 'nullable|boolean',
            'is_worker_need_to_shown' => 'nullable|boolean',
            'is_emp_group_task_need_to_shown' => 'nullable|boolean',
            'is_day_grand_rpt_need_shown' => 'nullable|boolean',

            'is_need_pink_box_shown' => 'nullable|boolean',
            'is_need_order_status_shown' => 'nullable|boolean',
            'is_need_role_wise_cash_rpt_shown' => 'nullable|boolean',

            'is_need_to_retailer_shown' => 'nullable|boolean',
            'is_need_grosswgt_print_shown' => 'nullable|boolean',
            'is_cus_fitem_pur_out_shown' => 'nullable|boolean',
            'is_cus_fitem_pur_in_shown' => 'nullable|boolean',

            'is_need_show_order_display_in_head_login' => 'nullable|boolean',
            'is_metal_stock_shown' => 'nullable|boolean',

            // Numeric fields
            'grams_grand_total' => 'nullable|numeric',
            'purity_grand_total' => 'nullable|numeric',
            'stone_weight_grand_total' => 'nullable|numeric',
            'beads_weight_grand_total' => 'nullable|numeric',
            'net_weight_grand_total' => 'nullable|numeric',
            'gross_weight_grand_total' => 'nullable|numeric',
            'beads_stone_weight_grand_total' => 'nullable|numeric',

            'stone_cost_grand_total' => 'nullable|numeric',
            'beads_cost_grand_total' => 'nullable|numeric',
            'beads_stone_cost_grand_total' => 'nullable|numeric',

            'order_grand_total' => 'nullable|numeric',
            'order_grand_no_of_pcs' => 'nullable|integer',

            'per_day_salary' => 'nullable|numeric',
            'rak_cash_balance' => 'nullable|numeric',

            'allot_value' => 'nullable|numeric',
            'rak_rtgs_balance' => 'nullable|numeric',

            'credit_out_limit' => 'nullable|numeric',
            'credit_in_limit' => 'nullable|numeric',

            'temp_gram_credit_limit' => 'nullable|numeric',
            'temp_purity_credit_limit' => 'nullable|numeric',

            // Date fields
            'last_txn_date' => 'nullable|date',
            'last_cash_txn_date' => 'nullable|date',
            'last_rtgs_txn_date' => 'nullable|date',
            'credit_limit_updated_at' => 'nullable|date',

            // Other fields
            'report_password' => 'nullable|string|max:250',
            'otp' => 'nullable|string|max:4',
            'need_roles_in_rpt_shown' => 'nullable|string|max:2000',

            'attendance_user_id' => 'nullable|integer',
            'top_print_out' => 'nullable|string|max:100',

            'incentive_sent_to' => 'nullable|integer',
            'vendor_barcode_group_id' => 'nullable|integer',

            'vendor_short_name' => 'nullable|string|max:100',

            'credit_limit_remarks' => 'nullable|string',

            'credit_limit_updated_by' => 'nullable|integer',

            'default_mc_choice' =>
                'nullable|in:RETAILER,WHOLESALER',

            'page_access' =>
                'nullable|string',

            'is_admin_head' =>
                'nullable|integer',

            'main_head_id' =>
                'nullable|integer',
        ]);


        /*
        |--------------------------------------------------------------------------
        | DEFAULT VALUES FOR NOT NULL COLUMNS
        |--------------------------------------------------------------------------
        */

        $defaults = [

            'is_active' => true,
            'is_delete' => false,
            'is_billable' => false,
            'is_create_order_shown' => false,

            'grams_grand_total' => 0,
            'purity_grand_total' => 0,

            'order_grand_total' => 0,
            'order_grand_no_of_pcs' => 0,

            'is_salary_person' => false,
            'per_day_salary' => 0,

            'rak_cash_balance' => 0,

            'is_gold_cal_enabled' => true,
            'is_cash_cal_enabled' => true,
            'is_wastage_cal_enabled' => true,

            'is_customerfitem_cal_enabled' => false,
            'is_customerfitem_cal_in_enabled' => false,

            'is_otp_verified' => false,

            'rak_rtgs_balance' => 0,

            'is_polish_needed' => false,
            'is_wa_delivery_stock_needed' => false,
            'is_polish_chk_need_shown' => false,
            'is_delivery_chk_need_shown' => false,

            'is_cashamt_thermal_shown' => false,
            'is_customer_touch_need_shown' => false,
            'is_complete_history_need_shown' => false,
            'is_create_order_need_to_shown' => false,
            'is_cash_mngmt_need_to_shown' => false,
            'is_freeze_entry_need_to_shown' => false,
            'is_admin_login_otp_need_to_shown' => false,
            'is_customer_cmts_need_to_shown' => false,
            'is_outside_need_to_shown' => false,
            'is_tally_need_to_shown' => false,
            'is_die_num_search_need_to_shown' => false,

            'is_con_box_rpt_need_to_shown' => false,
            'is_box_tot_rpt_need_to_shown' => false,
            'is_ob_cb_rpt_need_to_shown' => false,
            'is_gallery_need_to_shown' => false,
            'is_worker_need_to_shown' => false,
            'is_emp_group_task_need_to_shown' => false,
            'is_day_grand_rpt_need_shown' => false,

            'is_need_pink_box_shown' => false,
            'is_need_order_status_shown' => false,
            'is_need_role_wise_cash_rpt_shown' => false,

            'is_need_to_retailer_shown' => false,
            'is_need_grosswgt_print_shown' => false,
            'is_cus_fitem_pur_out_shown' => false,
            'is_cus_fitem_pur_in_shown' => false,

            'is_admin_head' => 0,
        ];

        /*
        |--------------------------------------------------------------------------
        | Apply defaults only when value is missing
        |--------------------------------------------------------------------------
        */

        foreach ($defaults as $field => $value) {
            if (!array_key_exists($field, $validated)) {
                $validated[$field] = $value;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Create User
        |--------------------------------------------------------------------------
        */

        $user = UserDetail::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User detail created successfully',
            'data' => $user
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | GET SINGLE USER DETAIL
    |--------------------------------------------------------------------------
    */

    #[OA\Get(
        path: '/api/user-details/{user_id}',
        operationId: 'getUserDetailById',
        tags: ['User Details'],
        summary: 'Get user detail by ID',

        parameters: [
            new OA\Parameter(
                name: 'user_id',
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
    public function show($user_id): JsonResponse
    {
        $user = UserDetail::find($user_id);

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


    /*
    |--------------------------------------------------------------------------
    | UPDATE USER DETAIL
    |--------------------------------------------------------------------------
    */

    #[OA\Put(
        path: '/api/user-details/{user_id}',
        operationId: 'updateUserDetail',
        tags: ['User Details'],
        summary: 'Update user detail',

        parameters: [
            new OA\Parameter(
                name: 'user_id',
                description: 'User ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        example: 'Mohamed Azar Updated'
                    ),

                    new OA\Property(
                        property: 'user_name',
                        type: 'string',
                        example: 'azar'
                    ),

                    new OA\Property(
                        property: 'phone_no',
                        type: 'string',
                        example: '9876543210'
                    ),

                    new OA\Property(
                        property: 'role',
                        type: 'string',
                        example: 'Manager'
                    ),

                    new OA\Property(
                        property: 'category_name',
                        type: 'string',
                        enum: ['GRAMS', 'PURITY', 'BOTH'],
                        example: 'BOTH'
                    ),

                    new OA\Property(
                        property: 'is_active',
                        type: 'boolean',
                        example: true
                    ),

                    new OA\Property(
                        property: 'is_customerfitem_cal_enabled',
                        type: 'boolean',
                        example: false
                    ),

                    new OA\Property(
                        property: 'is_customerfitem_cal_in_enabled',
                        type: 'boolean',
                        example: false
                    )
                ]
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
    public function update(
        Request $request,
        $user_id
    ): JsonResponse {

        $user = UserDetail::find($user_id);

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
                $user_id . ',user_id',

            'password_hash' =>
                'sometimes|string|max:255',

            'address' =>
                'sometimes|string|max:400',

            'signature' =>
                'sometimes|string|max:45',

            'code' =>
                'sometimes|string|max:255',

            'phone_no' =>
                'sometimes|string|max:15|unique:user_details,phone_no,' .
                $user_id . ',user_id',

            'remarks' => 'nullable|string|max:255',
            'proff' => 'sometimes|string|max:155',
            'role' => 'sometimes|string|max:50',

            'customer_commants' =>
                'nullable|string|max:1500',

            'mailing_name' =>
                'sometimes|string|max:255',

            'image_url' =>
                'nullable|string|max:255',

            'profile_image' =>
                'nullable|string',

            'category_name' =>
                'sometimes|in:GRAMS,PURITY,BOTH',

            'system_id' =>
                'sometimes|string|max:255|unique:user_details,system_id,' .
                $user_id . ',user_id',

            'is_active' => 'sometimes|boolean',
            'is_delete' => 'sometimes|boolean',
            'is_billable' => 'sometimes|boolean',
            'is_create_order_shown' => 'sometimes|boolean',

            'is_customerfitem_cal_enabled' =>
                'sometimes|boolean',

            'is_customerfitem_cal_in_enabled' =>
                'sometimes|boolean',

            'is_gold_cal_enabled' =>
                'sometimes|boolean',

            'is_cash_cal_enabled' =>
                'sometimes|boolean',

            'is_wastage_cal_enabled' =>
                'sometimes|boolean',

            'is_otp_verified' =>
                'sometimes|boolean',

            'grams_grand_total' =>
                'sometimes|numeric',

            'purity_grand_total' =>
                'sometimes|numeric',

            'order_grand_total' =>
                'sometimes|numeric',

            'order_grand_no_of_pcs' =>
                'sometimes|integer',

            'per_day_salary' =>
                'sometimes|numeric',

            'rak_cash_balance' =>
                'sometimes|numeric',

            'rak_rtgs_balance' =>
                'sometimes|numeric',

            'default_mc_choice' =>
                'nullable|in:RETAILER,WHOLESALER',

            'page_access' =>
                'nullable|string',

            'is_admin_head' =>
                'sometimes|integer',

            'main_head_id' =>
                'nullable|integer'
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User detail updated successfully',
            'data' => $user->fresh()
        ], 200);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE USER DETAIL
    |--------------------------------------------------------------------------
    */

    #[OA\Delete(
        path: '/api/user-details/{user_id}',
        operationId: 'deleteUserDetail',
        tags: ['User Details'],
        summary: 'Delete user detail',

        parameters: [
            new OA\Parameter(
                name: 'user_id',
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
    public function destroy($user_id): JsonResponse
    {
        $user = UserDetail::find($user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User detail not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User detail deleted successfully'
        ], 200);
    }
}