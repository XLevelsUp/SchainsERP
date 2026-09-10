<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhoneBookResource;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PhoneBookController extends Controller
{
    /**
     * Display a listing of the phone book contacts.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = UserDetail::with('role');

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                      ->orWhere('phone_no', 'ILIKE', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('role_id')) {
                $query->where('role_id', $request->role_id);
            }

            $perPage = $request->input('per_page', 50);
            
            $sort = $request->input('sort', 'name');
            if ($sort === '-name') $query->orderBy('name', 'desc');
            elseif ($sort === 'name') $query->orderBy('name', 'asc');
            elseif ($sort === '-phone_no') $query->orderBy('phone_no', 'desc');
            elseif ($sort === 'phone_no') $query->orderBy('phone_no', 'asc');
            elseif ($sort === '-is_active') $query->orderBy('is_active', 'desc');
            elseif ($sort === 'is_active') $query->orderBy('is_active', 'asc');
            else $query->orderBy('name', 'asc');

            $contacts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Phone book retrieved successfully.',
                'data' => [
                    'current_page' => $contacts->currentPage(),
                    'data'         => PhoneBookResource::collection($contacts->items()),
                    'total'        => $contacts->total(),
                    'per_page'     => $contacts->perPage(),
                    'last_page'    => $contacts->lastPage(),
                    'next_page_url' => $contacts->nextPageUrl(),
                    'prev_page_url' => $contacts->previousPageUrl(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve phone book.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created contact.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:55',
            'phone_no' => 'required|string|max:20',
            'role_id' => 'required|integer',
            'is_active' => 'boolean',
            'address' => 'nullable|string',
            'remarks' => 'nullable|string',
            'profile_image' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            // Inject mandatory fields for UserDetail
            $data['user_name'] = 'pb_' . time() . rand(10,99);
            $data['password_hash'] = bcrypt('phonebook123');
            $data['signature'] = '-';
            $data['code'] = '-';
            $data['proff'] = '-';
            $data['mailing_name'] = $data['name'];
            $data['category_name'] = $request->input('category_name', 'GRAMS'); // must be GRAMS|PURITY|BOTH
            $data['system_id'] = '-';
            
            // Boolean fields
            $data['is_active'] = $request->input('is_active', true);
            $data['is_delete'] = false;
            $data['is_billable'] = true;
            $data['is_customerfitem_cal_enabled'] = false;
            $data['is_customerfitem_cal_in_enabled'] = false;
            $data['is_create_order_shown'] = false;
            $data['is_salary_person'] = false;
            $data['is_gold_cal_enabled'] = true;
            $data['is_cash_cal_enabled'] = true;
            $data['is_wastage_cal_enabled'] = true;
            $data['is_otp_verified'] = false;
            
            $data['is_remainder_shown'] = false;
            $data['is_delivery_item_shown'] = false;
            $data['is_polish_needed'] = false;
            $data['is_wa_delivery_stock_needed'] = false;
            $data['is_polish_chk_need_shown'] = false;
            $data['is_delivery_chk_need_shown'] = false;
            $data['is_cashamt_thermal_shown'] = false;
            $data['is_customer_touch_need_shown'] = false;
            $data['is_complete_history_need_shown'] = false;
            $data['is_create_order_need_to_shown'] = false;
            $data['is_cash_mngmt_need_to_shown'] = false;
            $data['is_freeze_entry_need_to_shown'] = false;
            $data['is_admin_login_otp_need_to_shown'] = false;
            $data['is_customer_cmts_need_to_shown'] = false;
            $data['is_outside_need_to_shown'] = false;
            $data['is_tally_need_to_shown'] = false;
            $data['is_die_num_search_need_to_shown'] = false;
            $data['is_con_box_rpt_need_to_shown'] = false;
            $data['is_box_tot_rpt_need_to_shown'] = false;
            $data['is_ob_cb_rpt_need_to_shown'] = false;
            $data['is_gallery_need_to_shown'] = false;
            $data['is_worker_need_to_shown'] = false;
            $data['is_emp_group_task_need_to_shown'] = false;
            $data['is_day_grand_rpt_need_shown'] = false;
            $data['is_need_pink_box_shown'] = false;
            $data['is_need_order_status_shown'] = false;
            $data['is_need_role_wise_cash_rpt_shown'] = false;
            $data['need_roles_in_rpt_shown'] = false;
            $data['is_need_to_retailer_shown'] = false;
            $data['is_need_grosswgt_print_shown'] = false;
            $data['is_cus_fitem_pur_out_shown'] = false;
            $data['is_cus_fitem_pur_in_shown'] = false;
            $data['is_need_show_order_display_in_head_login'] = false;
            $data['is_metal_stock_shown'] = false;

            if ($request->hasFile('profile_image')) {
                $path = $request->file('profile_image')->store('profile_images', 'public');
                $data['profile_image'] = $path;
            }

            $user = UserDetail::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Contact created successfully.',
                'data' => $user
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create contact.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a contact.
     */
    public function show($id): JsonResponse
    {
        $user = UserDetail::with('role')->find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Contact retrieved.', 'data' => $user], 200);
    }

    /**
     * Update a contact.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = UserDetail::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:55',
            'phone_no' => 'sometimes|string|max:20',
            'role_id' => 'sometimes|integer',
            'is_active' => 'boolean',
            'address' => 'nullable|string',
            'remarks' => 'nullable|string',
            'profile_image' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();

            if ($request->hasFile('profile_image')) {
                if ($user->profile_image) {
                    Storage::disk('public')->delete($user->profile_image);
                }
                $path = $request->file('profile_image')->store('profile_images', 'public');
                $data['profile_image'] = $path;
            }

            $user->update($data);

            return response()->json(['success' => true, 'message' => 'Contact updated successfully.', 'data' => $user], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update contact.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a contact.
     */
    public function destroy($id): JsonResponse
    {
        $user = UserDetail::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Contact not found.'], 404);
        }
        
        try {
            $user->is_delete = true;
            $user->is_active = false;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Contact deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete contact.', 'error' => $e->getMessage()], 500);
        }
    }
}
