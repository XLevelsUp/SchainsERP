<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateCcRequest;
use App\Models\UserDetail;
use App\Rules\HeadUser;
use App\Support\AdminAccess;
use App\Support\HeadUsers;
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
    /**
     * Rules for the user-form sections added 2026-11-21 (joining date,
     * customer touches), shared by store() and update(). Which of them a
     * role may send is decided before the controller, by ApplyUserFormProfile.
     */
    private static function formSectionRules(): array
    {
        return [
            'joining_date' => 'nullable|date',
            'customer_touch_mappings' => 'nullable|array',
            'customer_touch_mappings.*.customer_touch_id' => 'required|integer|distinct|exists:customer_touch,item_id',
            'customer_touch_mappings.*.is_active' => 'sometimes|boolean',

            // HEAD form (2026-11-21).
            'head_user_mappings' => 'nullable|array',
            'head_user_mappings.*.employee_id' => 'required|integer|distinct|exists:user_details,user_id',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'aadhar_back_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'aadhar_number' => 'nullable|string|max:20',
            'per_day_salary' => 'nullable|numeric|min:0|max:99999999',
            // Set or replaced, never read back; stored hashed.
            'report_password' => 'nullable|string|min:4|max:100',
            'is_item_change_need_shown' => 'sometimes|boolean',
            'is_item_conversion_need_shown' => 'sometimes|boolean',
            'is_phone_book_need_shown' => 'sometimes|boolean',
        ];
    }

    /*
    | Phone numbers (agreed 2026-11-23): exactly 10 digits, a leading 0
    | allowed (the column is text, so it is kept), and no two users may share
    | one (also a unique index). Applies to every create and edit: the admin
    | console's Users form and the app's Add Retailer. An older number of
    | another length has to be corrected the next time that user is saved.
    */
    private const PHONE_PATTERN = 'regex:/^[0-9]{10}$/';

    private const PHONE_MESSAGES = [
        'phone_no.regex' => 'Phone number must be exactly 10 digits.',
        'phone_no.unique' => 'Another user already has this phone number.',
    ];

    /*
    | Only HEAD users may be chosen as a user's heads (Head lists, Cash
    | heads) or listed as a head's Head users (agreed 2026-11-23). Checked
    | for the admin console only: the app's Add Retailer maps the retailer to
    | whoever adds it, which may be an "act as head" employee, and
    | EnsureQuickAddCustomer already limits it to that.
    */
    private static function requireHeadUsers(\Illuminate\Validation\Validator $validator, Request $request): void
    {
        if (!AdminAccess::isAdmin($request->user())) {
            return;
        }

        $rule = new HeadUser();
        $validator->addRules([
            'head_mappings.*.head_id' => [$rule],
            'cash_head_mappings.*.head_id' => [$rule],
            'head_user_mappings.*.employee_id' => [$rule],
        ]);
    }

    /** Fields of the HEAD form stored straight from the request, on create and edit. */
    private const HEAD_FORM_FIELDS = ['aadhar_number', 'per_day_salary'];

    /** The 2026-11-21 permissions (default off). */
    private const NEW_PERMISSION_FLAGS = [
        'is_item_change_need_shown',
        'is_item_conversion_need_shown',
        'is_phone_book_need_shown',
    ];

    /**
     * Stores an uploaded image for a user column (image_url,
     * aadhar_back_image), deleting the file it replaces. Null when none was
     * sent, so the stored one stays.
     */
    private function storeUserImage(Request $request, string $field, ?UserDetail $user = null): ?string
    {
        if (!$request->hasFile($field)) {
            return null;
        }

        if ($user && $user->{$field} && Storage::disk('public')->exists($user->{$field})) {
            Storage::disk('public')->delete($user->{$field});
        }

        return $request->file($field)->store('images', 'public');
    }

    /** The HEAD form's extra columns, from the request (store / update). */
    private function headFormData(Request $request, ?UserDetail $user = null): array
    {
        $data = [];

        foreach (self::HEAD_FORM_FIELDS as $field) {
            if ($request->exists($field)) {
                $data[$field] = $field === 'per_day_salary'
                    ? ($request->input($field) ?? 0)
                    : $request->input($field);
            }
        }

        foreach (self::NEW_PERMISSION_FLAGS as $flag) {
            if ($request->exists($flag)) {
                $data[$flag] = $request->boolean($flag);
            }
        }

        // Blank keeps the current one.
        if ($request->filled('report_password')) {
            $data['report_password'] = Hash::make((string) $request->input('report_password'));
        }

        foreach (['image_url', 'aadhar_back_image'] as $field) {
            if ($path = $this->storeUserImage($request, $field, $user)) {
                $data[$field] = $path;
            }
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL USERS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        try {

            $actingUserId = (int) $request->user()->user_id;

            // ?mapped_only=1 (the Stock screen's pickers): only the signed-in
            // user and the users mapped to them, whatever their role. Always
            // the token's user: head_id and all/unfiltered are ignored with it.
            $mappedOnly = $request->boolean('mapped_only');
            $headId = (!$mappedOnly && $request->query('head_id')) ? (int) $request->query('head_id') : $actingUserId;

            $query = UserDetail::with([
                'itemMappings',
                'headEmployeeMappings',
                'cashHeadEmployeeMappings',
                'role'
            ])
            ->orderBy('user_id', 'desc');

            $module = $request->query('module', 'stock'); // Default to stock
            $isAll = !$mappedOnly && ($request->boolean('all') || $request->boolean('unfiltered'));

            // Apply mapped users filter for Head / Mapped queries
            if (!$isAll && $headId) {
                $actingUser = UserDetail::find($headId);
                $isHead = $actingUser && strtoupper((string) optional($actingUser->role)->role) === 'HEAD';

                if ($isHead || $request->has('head_id') || $mappedOnly) {
                    if (strtolower($module) === 'cash') {
                        $mappedIds = CashHeadEmployeeMapping::where('head_id', $headId)->pluck('employee_id')->toArray();
                    } else {
                        $mappedIds = HeadEmployeeMapping::where('head_id', $headId)->pluck('employee_id')->toArray();
                    }

                    if ($request->has('type')) {
                        $requestedType = strtoupper($request->type);
                        if ($requestedType === 'EMPLOYEE' || $requestedType === 'WORKER') {
                            $query->whereIn('user_id', $mappedIds);
                        } elseif ($requestedType === 'HEAD') {
                            $query->where('user_id', $headId);
                        }
                    } else {
                        $query->whereIn('user_id', array_unique(array_merge([$headId], $mappedIds)));
                    }
                }
            }

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

            $formattedUsers = $users->map(function ($user) use ($module) {
                $data = [
                    'id' => $user->user_id,
                    'full_name' => $user->name,
                    'type' => $user->role ? $user->role->role : null,
                    'role' => $user->role ? $user->role->role : null,
                    'category_name' => $user->category_name,
                    'retailer_user_name' => 'Normal user',
                    'phone_number' => $user->phone_no,
                    // For the admin console's Users list (2026-11-21).
                    'user_name' => $user->user_name,
                    'role_id' => $user->role_id,
                    'is_salary_person' => (bool) $user->is_salary_person,
                    'per_day_salary' => $user->per_day_salary,
                ];

                // Balances only for heads, or for the signed-in user's own row
                // (App\Support\BalanceVisibility).
                $showBalance = \App\Support\BalanceVisibility::canSeeBalancesOf($user->user_id);

                if (strtolower($module) === 'cash') {
                    if ($showBalance) {
                        $data['hand_cash'] = sprintf('%0.2f', $user->rak_cash_balance ?? 0);
                        $data['rtgs_cash'] = sprintf('%0.2f', $user->rak_rtgs_balance ?? 0);
                    }
                    $data['last_cash_txn_date'] = $user->last_cash_txn_date ? date('Y-m-d H:i:s', strtotime($user->last_cash_txn_date)) : null;
                } else {
                    if ($showBalance) {
                        $data['gm'] = sprintf('%0.3f', $user->grams_grand_total ?? 0);
                        $data['purity'] = sprintf('%0.3f', $user->purity_grand_total ?? 0);
                    }
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
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
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
                'required_without:new_password|nullable|string|min:6',

            'new_password' =>
                'nullable|string|min:6',

            'confirm_password' =>
                'nullable|string',

            'password_confirmation' =>
                'nullable|string',

            'address' =>
                'required|string|max:400',

            'signature' =>
                'required|string|max:45',

            'code' =>
                'required|string|max:255',

            'phone_no' =>
                'required|string|' . self::PHONE_PATTERN . '|unique:user_details,phone_no',

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

            'is_remainder_shown' =>
                'sometimes|boolean',
            'is_delivery_item_shown' =>
                'sometimes|boolean',
            'is_polish_needed' =>
                'sometimes|boolean',
            'is_wa_delivery_stock_needed' =>
                'sometimes|boolean',
            'is_polish_chk_need_shown' =>
                'sometimes|boolean',
            'is_delivery_chk_need_shown' =>
                'sometimes|boolean',
            'is_cashamt_thermal_shown' =>
                'sometimes|boolean',
            'is_customer_touch_need_shown' =>
                'sometimes|boolean',
            'is_complete_history_need_shown' =>
                'sometimes|boolean',
            'is_create_order_need_to_shown' =>
                'sometimes|boolean',
            'is_cash_mngmt_need_to_shown' =>
                'sometimes|boolean',
            'is_freeze_entry_need_to_shown' =>
                'sometimes|boolean',
            'is_admin_login_otp_need_to_shown' =>
                'sometimes|boolean',
            'is_act_as_head_enabled' =>
                'sometimes|boolean',
            'is_customer_cmts_need_to_shown' =>
                'sometimes|boolean',
            'is_outside_need_to_shown' =>
                'sometimes|boolean',
            'is_tally_need_to_shown' =>
                'sometimes|boolean',
            'is_die_num_search_need_to_shown' =>
                'sometimes|boolean',
            'is_con_box_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_box_tot_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_ob_cb_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_gallery_need_to_shown' =>
                'sometimes|boolean',
            'is_worker_need_to_shown' =>
                'sometimes|boolean',
            'is_emp_group_task_need_to_shown' =>
                'sometimes|boolean',
            'is_day_grand_rpt_need_shown' =>
                'sometimes|boolean',
            'is_need_pink_box_shown' =>
                'sometimes|boolean',
            'is_need_order_status_shown' =>
                'sometimes|boolean',
            'is_need_role_wise_cash_rpt_shown' =>
                'sometimes|boolean',
            'is_need_to_retailer_shown' =>
                'sometimes|boolean',
            'is_need_grosswgt_print_shown' =>
                'sometimes|boolean',
            'is_cus_fitem_pur_out_shown' =>
                'sometimes|boolean',
            'is_cus_fitem_pur_in_shown' =>
                'sometimes|boolean',
            'is_need_show_order_display_in_head_login' =>
                'sometimes|boolean',
            'is_metal_stock_shown' =>
                'sometimes|boolean',

            'is_remainder_shown' =>
                'sometimes|boolean',
            'is_delivery_item_shown' =>
                'sometimes|boolean',
            'is_polish_needed' =>
                'sometimes|boolean',
            'is_wa_delivery_stock_needed' =>
                'sometimes|boolean',
            'is_polish_chk_need_shown' =>
                'sometimes|boolean',
            'is_delivery_chk_need_shown' =>
                'sometimes|boolean',
            'is_cashamt_thermal_shown' =>
                'sometimes|boolean',
            'is_customer_touch_need_shown' =>
                'sometimes|boolean',
            'is_complete_history_need_shown' =>
                'sometimes|boolean',
            'is_create_order_need_to_shown' =>
                'sometimes|boolean',
            'is_cash_mngmt_need_to_shown' =>
                'sometimes|boolean',
            'is_freeze_entry_need_to_shown' =>
                'sometimes|boolean',
            'is_admin_login_otp_need_to_shown' =>
                'sometimes|boolean',
            'is_act_as_head_enabled' =>
                'sometimes|boolean',
            'is_customer_cmts_need_to_shown' =>
                'sometimes|boolean',
            'is_outside_need_to_shown' =>
                'sometimes|boolean',
            'is_tally_need_to_shown' =>
                'sometimes|boolean',
            'is_die_num_search_need_to_shown' =>
                'sometimes|boolean',
            'is_con_box_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_box_tot_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_ob_cb_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_gallery_need_to_shown' =>
                'sometimes|boolean',
            'is_worker_need_to_shown' =>
                'sometimes|boolean',
            'is_emp_group_task_need_to_shown' =>
                'sometimes|boolean',
            'is_day_grand_rpt_need_shown' =>
                'sometimes|boolean',
            'is_need_pink_box_shown' =>
                'sometimes|boolean',
            'is_need_order_status_shown' =>
                'sometimes|boolean',
            'is_need_role_wise_cash_rpt_shown' =>
                'sometimes|boolean',
            'is_need_to_retailer_shown' =>
                'sometimes|boolean',
            'is_need_grosswgt_print_shown' =>
                'sometimes|boolean',
            'is_cus_fitem_pur_out_shown' =>
                'sometimes|boolean',
            'is_cus_fitem_pur_in_shown' =>
                'sometimes|boolean',
            'is_need_show_order_display_in_head_login' =>
                'sometimes|boolean',
            'is_metal_stock_shown' =>
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

            // exists: an unknown head id used to fail the insert's foreign key
            // as a 500.
            'head_mappings.*.head_id' =>
                'required|integer|distinct|exists:user_details,user_id',


            /*
            |--------------------------------------------------------------------------
            | CASH HEAD MAPPINGS
            |--------------------------------------------------------------------------
            */

            'cash_head_mappings' =>
                'nullable|array',

            'cash_head_mappings.*.head_id' =>
                'required|integer|distinct|exists:user_details,user_id',

            ...self::formSectionRules(),
        ], self::PHONE_MESSAGES);

        self::requireHeadUsers($validator, $request);

        $validator->after(function ($validator) use ($request) {
            $pwd = $request->input('password') ?? $request->input('new_password');
            $confirmPwd = $request->input('confirm_password') 
                ?? $request->input('password_confirmation');

            if (!empty($pwd) && !empty($confirmPwd) && $pwd !== $confirmPwd) {
                $validator->errors()->add('confirm_password', 'The password confirmation does not match.');
            }
        });

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        /*
        | A user with "Can act as head" may create users (e.g. Add Retailer on
        | the Stock screen), but only a head may create another head or hand
        | out that permission (BACKEND_TODO §26, agreed 2026-11-12). Admins
        | create and set up users (agreed 2026-11-20), so they may too. In the
        | app this route only takes Add Retailer's customer anyway
        | (EnsureQuickAddCustomer).
        */
        if (!\App\Support\BalanceVisibility::viewerIsHead()
            && !\App\Support\AdminAccess::isAdmin($request->user())) {
            $headRoleIds = \App\Models\Role::whereRaw('UPPER(role) = ?', ['HEAD'])
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if (in_array((string) $request->input('role_id'), $headRoleIds, true)
                || $request->boolean('is_act_as_head_enabled')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only a head can create a head or give the "Can act as head" permission.',
                ], 403);
            }
        }


        try {

            $user = DB::transaction(function () use ($request) {

                /*
                |--------------------------------------------------------------------------
                | USER DATA
                |--------------------------------------------------------------------------
                */

                $plainPassword = $request->input('password') ?? $request->input('new_password');

                $userData = [

                    'name' =>
                        $request->name,

                    'user_name' =>
                        $request->user_name,

                    'password_hash' =>
                        Hash::make($plainPassword),

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

                    'joining_date' =>
                        $request->input('joining_date'),


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

                    'is_remainder_shown' =>
                        $request->input('is_remainder_shown', false),
                    'is_delivery_item_shown' =>
                        $request->input('is_delivery_item_shown', false),
                    'is_polish_needed' =>
                        $request->input('is_polish_needed', false),
                    'is_wa_delivery_stock_needed' =>
                        $request->input('is_wa_delivery_stock_needed', false),
                    'is_polish_chk_need_shown' =>
                        $request->input('is_polish_chk_need_shown', false),
                    'is_delivery_chk_need_shown' =>
                        $request->input('is_delivery_chk_need_shown', false),
                    'is_cashamt_thermal_shown' =>
                        $request->input('is_cashamt_thermal_shown', false),
                    'is_customer_touch_need_shown' =>
                        $request->input('is_customer_touch_need_shown', false),
                    'is_complete_history_need_shown' =>
                        $request->input('is_complete_history_need_shown', false),
                    'is_create_order_need_to_shown' =>
                        $request->input('is_create_order_need_to_shown', false),
                    'is_cash_mngmt_need_to_shown' =>
                        $request->input('is_cash_mngmt_need_to_shown', false),
                    'is_freeze_entry_need_to_shown' =>
                        $request->input('is_freeze_entry_need_to_shown', false),
                    'is_admin_login_otp_need_to_shown' =>
                        $request->input('is_admin_login_otp_need_to_shown', false),
                    'is_act_as_head_enabled' =>
                        $request->input('is_act_as_head_enabled', false),
                    'is_customer_cmts_need_to_shown' =>
                        $request->input('is_customer_cmts_need_to_shown', false),
                    'is_outside_need_to_shown' =>
                        $request->input('is_outside_need_to_shown', false),
                    'is_tally_need_to_shown' =>
                        $request->input('is_tally_need_to_shown', false),
                    'is_die_num_search_need_to_shown' =>
                        $request->input('is_die_num_search_need_to_shown', false),
                    'is_con_box_rpt_need_to_shown' =>
                        $request->input('is_con_box_rpt_need_to_shown', false),
                    'is_box_tot_rpt_need_to_shown' =>
                        $request->input('is_box_tot_rpt_need_to_shown', false),
                    'is_ob_cb_rpt_need_to_shown' =>
                        $request->input('is_ob_cb_rpt_need_to_shown', false),
                    'is_gallery_need_to_shown' =>
                        $request->input('is_gallery_need_to_shown', false),
                    'is_worker_need_to_shown' =>
                        $request->input('is_worker_need_to_shown', false),
                    'is_emp_group_task_need_to_shown' =>
                        $request->input('is_emp_group_task_need_to_shown', false),
                    'is_day_grand_rpt_need_shown' =>
                        $request->input('is_day_grand_rpt_need_shown', false),
                    'is_need_pink_box_shown' =>
                        $request->input('is_need_pink_box_shown', false),
                    'is_need_order_status_shown' =>
                        $request->input('is_need_order_status_shown', false),
                    'is_need_role_wise_cash_rpt_shown' =>
                        $request->input('is_need_role_wise_cash_rpt_shown', false),
                    'is_need_to_retailer_shown' =>
                        $request->input('is_need_to_retailer_shown', false),
                    'is_need_grosswgt_print_shown' =>
                        $request->input('is_need_grosswgt_print_shown', false),
                    'is_cus_fitem_pur_out_shown' =>
                        $request->input('is_cus_fitem_pur_out_shown', false),
                    'is_cus_fitem_pur_in_shown' =>
                        $request->input('is_cus_fitem_pur_in_shown', false),
                    'is_need_show_order_display_in_head_login' =>
                        $request->input('is_need_show_order_display_in_head_login', false),
                    'is_metal_stock_shown' =>
                        $request->input('is_metal_stock_shown', false),

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

                // HEAD form (2026-11-21): Aadhar number / back image, image,
                // per-day salary, report password, the new permissions.
                $userData = array_merge($userData, $this->headFormData($request));

                $user = UserDetail::create($userData);


                /*
                |--------------------------------------------------------------------------
                | ITEM MAPPINGS
                |--------------------------------------------------------------------------
                */

                // Admins choose a new user's items, never the balances on them
                // (2026-11-21): their items start at zero, like the legacy form.
                $totalsFromRequest = !\App\Support\AdminAccess::isAdmin($request->user());

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
                                $totalsFromRequest
                                    ? ($mapping['item_grams_total'] ?? '0')
                                    : '0',

                            'item_purity_total' =>
                                $totalsFromRequest
                                    ? ($mapping['item_purity_total'] ?? '0')
                                    : '0',

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


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER TOUCH MAPPINGS (user form, 2026-11-21)
                |--------------------------------------------------------------------------
                */

                if ($request->filled('customer_touch_mappings')) {
                    app(\App\Services\UserMappingSyncService::class)
                        ->syncCustomerTouches($user, $request->input('customer_touch_mappings'));
                }

                // For a head: the users under them (HEAD form's "Head Users").
                if ($request->filled('head_user_mappings')) {
                    app(\App\Services\UserMappingSyncService::class)
                        ->syncHeadUsers($user, $request->input('head_user_mappings'));
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
            report($e);

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to create user and mappings',


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
                'customerTouchMappings',
                // A head's "Head users" are its links to other heads only;
                // links to employees and customers are managed from their
                // own Head lists (agreed 2026-11-23).
                'headUserMappings' => fn ($query) => $query->whereIn('employee_id', HeadUsers::ids()->all()),
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
            unset($userArray['password_hash']);
            unset($userArray['report_password']);
            unset($userArray['otp']);
            $userArray['password'] = '';
            $userArray['confirm_password'] = '';
            // Same rule as the model's toArray() (BalanceVisibility): these were
            // added after it and leaked any user's grams/purity to anyone.
            if (\App\Support\BalanceVisibility::canSeeBalancesOf($user->user_id)) {
                $userArray['gm'] = sprintf('%0.3f', $user->grams_grand_total ?? 0);
                $userArray['purity'] = sprintf('%0.3f', $user->purity_grand_total ?? 0);
            }
            $userArray['id'] = $user->user_id;
            $userArray['full_name'] = $user->name;
            $userArray['phone_number'] = $user->phone_no;
            $userArray['profile_img'] = $user->profile_image ? asset('storage/' . $user->profile_image) : null;
            $userArray['type'] = $user->role ? $user->role->role : null;
            // The report password is never sent back; the form only needs to
            // know whether one is set (2026-11-21).
            $userArray['has_report_password'] = !empty($user->report_password);

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

                    // HEAD form images (2026-11-21).
                    'image_url' => $user->image_url ? asset('storage/' . $user->image_url) : null,
                    'aadhar_back_image_url' => $user->aadhar_back_image ? asset('storage/' . $user->aadhar_back_image) : null,
                ]

            ], 200);

        } catch (\Exception $e) {
            report($e);

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to retrieve user',


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

                    'new_password' =>
                        'nullable|string|min:6',

                    'confirm_password' =>
                        'nullable|string',

                    'password_confirmation' =>
                        'nullable|string',

                    'confirm_new_password' =>
                        'nullable|string',

                    'new_password_confirmation' =>
                        'nullable|string',

                    'address' =>
                        'sometimes|string|max:400',

                    'signature' =>
                        'sometimes|string|max:45',

                    'code' =>
                        'sometimes|string|max:255',

                    'phone_no' =>
                        'sometimes|string|' . self::PHONE_PATTERN . '|unique:user_details,phone_no,' .
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
                    | HEADS (2026-11-21) — sent only when the admin changed them;
                    | replaces the user's set. Needs the special password
                    | (routes/admin.php). Null = none (multipart can't send []).
                    |--------------------------------------------------------------------------
                    */

                    'head_mappings' =>
                        'nullable|array',

                    'head_mappings.*.head_id' =>
                        'required|integer|distinct|exists:user_details,user_id',

                    'cash_head_mappings' =>
                        'nullable|array',

                    'cash_head_mappings.*.head_id' =>
                        'required|integer|distinct|exists:user_details,user_id',

                    ...self::formSectionRules(),


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

            'is_remainder_shown' =>
                'sometimes|boolean',
            'is_delivery_item_shown' =>
                'sometimes|boolean',
            'is_polish_needed' =>
                'sometimes|boolean',
            'is_wa_delivery_stock_needed' =>
                'sometimes|boolean',
            'is_polish_chk_need_shown' =>
                'sometimes|boolean',
            'is_delivery_chk_need_shown' =>
                'sometimes|boolean',
            'is_cashamt_thermal_shown' =>
                'sometimes|boolean',
            'is_customer_touch_need_shown' =>
                'sometimes|boolean',
            'is_complete_history_need_shown' =>
                'sometimes|boolean',
            'is_create_order_need_to_shown' =>
                'sometimes|boolean',
            'is_cash_mngmt_need_to_shown' =>
                'sometimes|boolean',
            'is_freeze_entry_need_to_shown' =>
                'sometimes|boolean',
            'is_admin_login_otp_need_to_shown' =>
                'sometimes|boolean',
            'is_act_as_head_enabled' =>
                'sometimes|boolean',
            'is_customer_cmts_need_to_shown' =>
                'sometimes|boolean',
            'is_outside_need_to_shown' =>
                'sometimes|boolean',
            'is_tally_need_to_shown' =>
                'sometimes|boolean',
            'is_die_num_search_need_to_shown' =>
                'sometimes|boolean',
            'is_con_box_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_box_tot_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_ob_cb_rpt_need_to_shown' =>
                'sometimes|boolean',
            'is_gallery_need_to_shown' =>
                'sometimes|boolean',
            'is_worker_need_to_shown' =>
                'sometimes|boolean',
            'is_emp_group_task_need_to_shown' =>
                'sometimes|boolean',
            'is_day_grand_rpt_need_shown' =>
                'sometimes|boolean',
            'is_need_pink_box_shown' =>
                'sometimes|boolean',
            'is_need_order_status_shown' =>
                'sometimes|boolean',
            'is_need_role_wise_cash_rpt_shown' =>
                'sometimes|boolean',
            'is_need_to_retailer_shown' =>
                'sometimes|boolean',
            'is_need_grosswgt_print_shown' =>
                'sometimes|boolean',
            'is_cus_fitem_pur_out_shown' =>
                'sometimes|boolean',
            'is_cus_fitem_pur_in_shown' =>
                'sometimes|boolean',
            'is_need_show_order_display_in_head_login' =>
                'sometimes|boolean',
            'is_metal_stock_shown' =>
                'sometimes|boolean',
                ],
                self::PHONE_MESSAGES
            );

            self::requireHeadUsers($validator, $request);

            $validator->after(function ($validator) use ($request) {
                $pwd = $request->input('password') ?? $request->input('new_password');
                $confirmPwd = $request->input('confirm_password') 
                    ?? $request->input('password_confirmation')
                    ?? $request->input('confirm_new_password')
                    ?? $request->input('new_password_confirmation');

                if (!empty($pwd) && !empty($confirmPwd) && $pwd !== $confirmPwd) {
                    $validator->errors()->add('confirm_password', 'The password confirmation does not match.');
                }
            });

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

            $sync = app(\App\Services\UserMappingSyncService::class);

            DB::transaction(function () use ($request, $user, $sync) {

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
                    'joining_date',

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
                    'is_remainder_shown',
                    'is_delivery_item_shown',
                    'is_polish_needed',
                    'is_wa_delivery_stock_needed',
                    'is_polish_chk_need_shown',
                    'is_delivery_chk_need_shown',
                    'is_cashamt_thermal_shown',
                    'is_customer_touch_need_shown',
                    'is_complete_history_need_shown',
                    'is_create_order_need_to_shown',
                    'is_cash_mngmt_need_to_shown',
                    'is_freeze_entry_need_to_shown',
                    'is_admin_login_otp_need_to_shown',
                    'is_act_as_head_enabled',
                    'is_customer_cmts_need_to_shown',
                    'is_outside_need_to_shown',
                    'is_tally_need_to_shown',
                    'is_die_num_search_need_to_shown',
                    'is_con_box_rpt_need_to_shown',
                    'is_box_tot_rpt_need_to_shown',
                    'is_ob_cb_rpt_need_to_shown',
                    'is_gallery_need_to_shown',
                    'is_worker_need_to_shown',
                    'is_emp_group_task_need_to_shown',
                    'is_day_grand_rpt_need_shown',
                    'is_need_pink_box_shown',
                    'is_need_order_status_shown',
                    'is_need_role_wise_cash_rpt_shown',
                    'is_need_to_retailer_shown',
                    'is_need_grosswgt_print_shown',
                    'is_cus_fitem_pur_out_shown',
                    'is_cus_fitem_pur_in_shown',
                    'is_need_show_order_display_in_head_login',
                    'is_metal_stock_shown',
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
                                    'is_otp_verified',
                                    'is_remainder_shown',
                                    'is_delivery_item_shown',
                                    'is_polish_needed',
                                    'is_wa_delivery_stock_needed',
                                    'is_polish_chk_need_shown',
                                    'is_delivery_chk_need_shown',
                                    'is_cashamt_thermal_shown',
                                    'is_customer_touch_need_shown',
                                    'is_complete_history_need_shown',
                                    'is_create_order_need_to_shown',
                                    'is_cash_mngmt_need_to_shown',
                                    'is_freeze_entry_need_to_shown',
                                    'is_admin_login_otp_need_to_shown',
                                    'is_act_as_head_enabled',
                                    'is_customer_cmts_need_to_shown',
                                    'is_outside_need_to_shown',
                                    'is_tally_need_to_shown',
                                    'is_die_num_search_need_to_shown',
                                    'is_con_box_rpt_need_to_shown',
                                    'is_box_tot_rpt_need_to_shown',
                                    'is_ob_cb_rpt_need_to_shown',
                                    'is_gallery_need_to_shown',
                                    'is_worker_need_to_shown',
                                    'is_emp_group_task_need_to_shown',
                                    'is_day_grand_rpt_need_shown',
                                    'is_need_pink_box_shown',
                                    'is_need_order_status_shown',
                                    'is_need_role_wise_cash_rpt_shown',
                                    'is_need_to_retailer_shown',
                                    'is_need_grosswgt_print_shown',
                                    'is_cus_fitem_pur_out_shown',
                                    'is_cus_fitem_pur_in_shown',
                                    'is_need_show_order_display_in_head_login',
                                    'is_metal_stock_shown',
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

                $newPassword = $request->input('password') ?? $request->input('new_password');

                if (!empty($newPassword)) {
                    $userData['password_hash'] = Hash::make($newPassword);
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


                // HEAD form (2026-11-21): only what was sent changes; a new
                // image replaces (and deletes) the old file.
                $userData = array_merge($userData, $this->headFormData($request, $user));


                /*
                |--------------------------------------------------------------------------
                | SAVE USER
                |--------------------------------------------------------------------------
                */

                $user->update($userData);

                

                /*
                |--------------------------------------------------------------------------
                | SYNC ITEM MAPPINGS (Yii2 Parity)
                |--------------------------------------------------------------------------
                | Replicates the Yii2 createMultiple/deleteAll sync pattern:
                | 1. Compute IDs to delete (old - incoming)
                | 2. Delete removed mappings
                | 3. Update existing / Create new mappings
                */

                // Only a head may change item mappings here: they carry running totals
                // (item_grams_total / item_purity_total) that non-heads can't see,
                // so a non-head's edit form would send them back blank and wipe
                // the balances (App\Support\BalanceVisibility).
                if ($request->has('item_mappings') && \App\Support\BalanceVisibility::viewerIsHead()) {

                    $newMappings = $request->input('item_mappings', []);

                    $existingIds = UsersItemsMapping::where(
                        'user_id', $user->user_id
                    )->pluck('id')->toArray();

                    $incomingIds = array_filter(
                        array_column($newMappings, 'id')
                    );

                    /*
                    | Delete removed mappings
                    */
                    $toDelete = array_diff($existingIds, $incomingIds);

                    if (!empty($toDelete)) {
                        UsersItemsMapping::whereIn('id', $toDelete)->delete();
                    }

                    /*
                    | Update existing / Create new mappings
                    */
                    foreach ($newMappings as $mapping) {

                        if (!empty($mapping['id'])) {

                            UsersItemsMapping::where('id', $mapping['id'])
                                ->where('user_id', $user->user_id)
                                ->update([
                                    'item_id'          => $mapping['item_id'],
                                    'item_grams_total' => $mapping['item_grams_total'] ?? '0',
                                    'item_purity_total' => $mapping['item_purity_total'] ?? '0',
                                    'is_primary'       => $mapping['is_primary'],
                                    'updated_at'       => now(),
                                ]);

                        } else {

                            UsersItemsMapping::create([
                                'item_id'          => $mapping['item_id'],
                                'user_id'          => $user->user_id,
                                'item_grams_total' => $mapping['item_grams_total'] ?? '0',
                                'item_purity_total' => $mapping['item_purity_total'] ?? '0',
                                'is_primary'       => $mapping['is_primary'],
                                'added_at'         => now(),
                            ]);
                        }
                    }
                } elseif ($request->has('item_mappings') && \App\Support\BalanceVisibility::viewerIsAdmin()) {
                    // Admins (2026-11-21): which items and which is primary;
                    // totals are kept, and an item holding a balance can't go.
                    $sync->syncItemsKeepingTotals($user, (array) $request->input('item_mappings', []));
                }


                /*
                |--------------------------------------------------------------------------
                | HEADS, CASH HEADS, CUSTOMER TOUCHES (user form, 2026-11-21)
                |--------------------------------------------------------------------------
                | Each is synced only when sent: the admin console sends the head
                | lists only when they changed (needing the special password), and
                | a role whose profile leaves a section out never gets it this far
                | (ApplyUserFormProfile).
                */

                if ($request->exists('head_mappings')) {
                    $sync->syncHeads($user, $request->input('head_mappings'), HeadEmployeeMapping::class);
                }

                if ($request->exists('cash_head_mappings')) {
                    $sync->syncHeads($user, $request->input('cash_head_mappings'), CashHeadEmployeeMapping::class);
                }

                if ($request->exists('customer_touch_mappings')) {
                    $sync->syncCustomerTouches($user, $request->input('customer_touch_mappings'));
                }

                // Head side (HEAD form's "Head Users"): sent only when changed,
                // needs the special password like any head assignment.
                if ($request->exists('head_user_mappings')) {
                    $sync->syncHeadUsers($user, $request->input('head_user_mappings'));
                }
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


        } catch (\Illuminate\Validation\ValidationException $e) {
            // Raised inside the transaction (e.g. removing an item that still
            // holds a balance): a 422 with the field error, not a 500.
            throw $e;
        } catch (\Exception $e) {
            report($e);

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to update user',


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

                // HEAD form images (2026-11-21).
                foreach (['image_url', 'aadhar_back_image'] as $field) {
                    if ($user->{$field} && Storage::disk('public')->exists($user->{$field})) {
                        Storage::disk('public')->delete($user->{$field});
                    }
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
            report($e);

            return response()->json([

                'success' => false,

                'message' =>
                    'Failed to delete user',


            ], 500);
        }
    }

    /**
     * Dedicated lightweight method to update Customer Comments (CC).
     * Replaces legacy Yii2 `updatecc` endpoint.
     */
    public function updateCc(UpdateCcRequest $request, $id): JsonResponse
    {
        try {
            $user = UserDetail::findOrFail($id);
            
            $user->customer_commants = $request->customer_commants;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Customer comments updated successfully',
                'data' => [
                    'user_id' => $user->user_id,
                    'customer_commants' => $user->customer_commants
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer comments',
            ], 500);
        }
    }
}
