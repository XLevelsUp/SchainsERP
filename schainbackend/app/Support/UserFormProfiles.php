<?php

namespace App\Support;

use App\Models\Role;

/**
 * Which parts of the admin console's user form each role gets (2026-11-21).
 *
 * Every user has a role, and each role allows a different set of fields and
 * permissions (the per-user `is_*` flags). The admin console reads these
 * profiles from GET /api/v1/admin/user-form-profiles to draw the form, and
 * ApplyUserFormProfile enforces them on save: input a role doesn't allow is
 * dropped before UserDetailController sees it.
 *
 * Always on the form, whatever the role: role, name, user name, phone,
 * password and category. Those are what store() needs to create anyone.
 * A role that doesn't sign in (signs_in false, CUSTOMER) has no name or
 * password on the form; ApplyUserFormProfile fills them in.
 *
 * A role with no profile below gets DEFAULT: every section and every flag,
 * i.e. the full form as it was before profiles existed. Roles are added here
 * as their field sets are agreed. ADMIN follows the legacy admin user form.
 */
final class UserFormProfiles
{
    /** Optional sections of the user form. */
    public const SECTIONS = [
        'contact',           // address, signature, mailing name
        'additional_phone',  // "Additional Phone No" (SALARY)
        'remarks',
        'customer_comments',
        'heads',             // the heads a user reports to (stock)
        'head_users',        // for a head: the users under them (stock)
        'cash_heads',        // the heads a user reports to (cash)
        'items',             // assigned items; totals always read-only
        'balances',          // grand totals and cash / RTGS balance, read-only
        'customer_touches',
        'image',             // the legacy form's "Image Url" (image_url)
        'profile_image',     // "Profile Picture"
        'proof',             // Aadhar number, Aadhar front & back images
        'salary',            // per-day salary (with the is_salary_person flag)
        'joining_date',
        'report_password',
        'account',           // billable / deleted
        'flags',             // the per-user permissions below
    ];

    /**
     * Request input each section owns, dropped when a role's profile leaves
     * the section out. `contact` owns nothing on purpose: store() requires
     * address / signature / mailing name, so the form sends placeholders.
     */
    public const SECTION_INPUTS = [
        'additional_phone' => ['additional_phone_no'],
        'remarks' => ['remarks'],
        'customer_comments' => ['customer_commants'],
        'heads' => ['head_mappings'],
        'head_users' => ['head_user_mappings'],
        'cash_heads' => ['cash_head_mappings'],
        'items' => ['item_mappings'],
        'customer_touches' => ['customer_touch_mappings'],
        'image' => ['image_url'],
        'profile_image' => ['profile_image'],
        'proof' => ['aadhar_number', 'aadhar_image', 'aadhar_back_image'],
        'salary' => ['per_day_salary'],
        'joining_date' => ['joining_date'],
        'report_password' => ['report_password'],
        'account' => ['is_billable', 'is_delete'],
    ];

    /**
     * The per-user permissions an admin can set. is_active is account state
     * (toggled from the user list) and is_otp_verified is written by the
     * login flow, so neither is listed.
     */
    public const FLAG_KEYS = [
        'is_act_as_head_enabled',
        'is_gold_cal_enabled', 'is_cash_cal_enabled', 'is_wastage_cal_enabled',
        'is_customerfitem_cal_enabled', 'is_customerfitem_cal_in_enabled',
        'is_metal_stock_shown', 'is_delivery_item_shown', 'is_polish_needed',
        'is_polish_chk_need_shown', 'is_delivery_chk_need_shown', 'is_wa_delivery_stock_needed',
        'is_cus_fitem_pur_in_shown', 'is_cus_fitem_pur_out_shown', 'is_need_grosswgt_print_shown',
        'is_die_num_search_need_to_shown',
        'is_create_order_shown', 'is_create_order_need_to_shown', 'is_need_order_status_shown',
        'is_need_show_order_display_in_head_login',
        'is_cash_mngmt_need_to_shown', 'is_cashamt_thermal_shown', 'is_need_role_wise_cash_rpt_shown',
        'is_ob_cb_rpt_need_to_shown', 'is_con_box_rpt_need_to_shown', 'is_box_tot_rpt_need_to_shown',
        'is_day_grand_rpt_need_shown', 'is_complete_history_need_shown', 'is_tally_need_to_shown',
        'is_customer_touch_need_shown', 'is_customer_cmts_need_to_shown', 'is_need_to_retailer_shown',
        'is_worker_need_to_shown', 'is_emp_group_task_need_to_shown', 'is_salary_person',
        'is_outside_need_to_shown', 'is_gallery_need_to_shown', 'is_need_pink_box_shown',
        'is_remainder_shown',
        'is_admin_login_otp_need_to_shown', 'is_freeze_entry_need_to_shown',
        // 2026-11-21, checked by the app when the user is signed in.
        'is_item_change_need_shown', 'is_item_conversion_need_shown', 'is_phone_book_need_shown',
    ];

    /**
     * Profiles by role name (upper case, as roles are stored).
     * lock_role_on_edit: the role can't be changed once the user exists (the
     * legacy ADMIN form shows it greyed out; the HEAD form lets it change).
     * signs_in (default true): false for records that never sign in
     * (CUSTOMER): the form has no Name or Password; ApplyUserFormProfile
     * copies the user name into the name and sets a random password.
     * default_category / default_primary_item: what a new user of the role
     * starts with on the form; the admin can change both.
     * default_flags: permissions a new user of the role starts with switched
     * on (SALARY: is_salary_person). joining_date_defaults_to_today: a new
     * user's joining date starts as today. Both are form defaults only.
     */
    private const PROFILES = [
        // The legacy salary user form (2026-11-23). Salary people are records
        // for wages and tracking: they don't sign in.
        'SALARY' => [
            'lock_role_on_edit' => false,
            'signs_in' => false,
            'default_category' => 'GRAMS',
            'default_primary_item' => 'GOLD',
            'default_flags' => ['is_salary_person'],
            'joining_date_defaults_to_today' => true,
            'sections' => [
                'additional_phone',
                'heads',
                'items',
                'balances',
                'image',
                'proof',
                'profile_image',
                'salary',
                'joining_date',
            ],
            'flags' => [
                'is_salary_person',               // Is salary person (with the salary section)
            ],
        ],

        // The legacy customer form (2026-11-23). Customers are records used to
        // track gold and cash, not accounts: they don't sign in.
        'CUSTOMER' => [
            'lock_role_on_edit' => false,
            'signs_in' => false,
            'default_category' => 'PURITY',
            'default_primary_item' => 'GOLD',
            'sections' => [
                'heads',
                'customer_comments',
                'items',
                'balances',
                'customer_touches',
                'joining_date',
                'flags',
            ],
            'flags' => [
                'is_remainder_shown',            // Cash reminder shown
                'is_wa_delivery_stock_needed',   // WhatsApp share needed in stock
                'is_delivery_item_shown',        // Delivery item shown (head)
                'is_delivery_chk_need_shown',    // Delivery checks shown in stock (employee)
                'is_cashamt_thermal_shown',      // Cash amount on thermal print in stock
                'is_customer_touch_need_shown',  // Customer touch shown
            ],
        ],

        // The legacy admin user form.
        'ADMIN' => [
            'lock_role_on_edit' => true,
            'sections' => [
                'heads',
                'customer_comments',
                'items',
                'balances',
                'customer_touches',
                'image',
                'joining_date',
                'flags',
            ],
            'flags' => [
                'is_remainder_shown',            // Cash reminder shown
                'is_wa_delivery_stock_needed',   // WhatsApp share needed in stock
                'is_delivery_item_shown',        // Delivery item shown (head)
                'is_delivery_chk_need_shown',    // Delivery checks shown in stock (employee)
                'is_cashamt_thermal_shown',      // Cash amount on thermal print in stock
                'is_customer_touch_need_shown',  // Customer touch shown
            ],
        ],

        // The legacy head user form. These permissions also decide what the
        // head sees in the app once signed in (EnsureUserPermission and the
        // app's screens).
        'HEAD' => [
            'lock_role_on_edit' => false,
            // Agreed 2026-11-24: a new head starts on BOTH, with GOLD as its
            // primary item (grams and purity 0).
            'default_category' => 'BOTH',
            'default_primary_item' => 'GOLD',
            'sections' => [
                'head_users',
                'items',
                'balances',
                'image',
                'proof',
                'profile_image',
                'salary',
                'joining_date',
                'report_password',
                'flags',
            ],
            'flags' => [
                'is_create_order_shown',          // Create order shown
                'is_remainder_shown',             // Cash reminder shown
                'is_wa_delivery_stock_needed',    // WhatsApp share needed in stock
                'is_delivery_item_shown',         // Delivery item shown (head)
                'is_delivery_chk_need_shown',     // Delivery checks shown in stock (employee)
                'is_cashamt_thermal_shown',       // Cash amount on thermal print in stock
                'is_customer_touch_need_shown',   // Customer touch shown
                'is_polish_needed',               // Polish needed shown (head)
                'is_polish_chk_need_shown',       // Polish checks shown in stock (employee)
                'is_complete_history_need_shown', // Complete history shown
                'is_item_change_need_shown',      // Item change in stock (head)
                'is_item_conversion_need_shown',  // Item conversion in stock (head)
                'is_cash_mngmt_need_to_shown',    // Cash management in stock (head)
                'is_phone_book_need_shown',       // Phone book shown
                'is_salary_person',               // Salary person (with the salary section)
            ],
        ],
    ];

    /** The resolved profile for a role name; DEFAULT when it has none. */
    public static function forRoleName(?string $roleName): array
    {
        $key = mb_strtoupper(trim((string) $roleName));
        $profile = self::PROFILES[$key] ?? null;

        if ($profile === null) {
            return [
                'role' => $key !== '' ? $key : null,
                'is_default' => true,
                'lock_role_on_edit' => false,
                'signs_in' => true,
                'default_category' => null,
                'default_primary_item' => null,
                'default_flags' => [],
                'joining_date_defaults_to_today' => false,
                'sections' => self::SECTIONS,
                'flags' => self::FLAG_KEYS,
            ];
        }

        return [
            'role' => $key,
            'is_default' => false,
            'lock_role_on_edit' => (bool) ($profile['lock_role_on_edit'] ?? false),
            'signs_in' => (bool) ($profile['signs_in'] ?? true),
            'default_category' => $profile['default_category'] ?? null,
            'default_primary_item' => $profile['default_primary_item'] ?? null,
            'default_flags' => array_values(array_intersect($profile['flags'], $profile['default_flags'] ?? [])),
            'joining_date_defaults_to_today' => (bool) ($profile['joining_date_defaults_to_today'] ?? false),
            'sections' => array_values(array_intersect(self::SECTIONS, $profile['sections'])),
            'flags' => array_values(array_intersect(self::FLAG_KEYS, $profile['flags'])),
        ];
    }

    public static function forRoleId(mixed $roleId): array
    {
        $name = $roleId !== null && $roleId !== '' ? Role::whereKey((int) $roleId)->value('role') : null;

        return self::forRoleName($name);
    }

    /** Everything the console needs to draw the form, for every role that exists. */
    public static function catalog(): array
    {
        $roles = Role::orderBy('id')->get(['id', 'role']);

        return [
            'sections' => self::SECTIONS,
            'flags' => self::FLAG_KEYS,
            'default' => self::forRoleName(null),
            'by_role_id' => $roles->mapWithKeys(fn (Role $role) => [
                (string) $role->id => self::forRoleName($role->role),
            ])->all(),
        ];
    }

    /**
     * Input keys a profile doesn't allow: flags it leaves out, and the input
     * of every section it leaves out.
     */
    public static function disallowedInputs(array $profile): array
    {
        if ($profile['is_default']) {
            return [];
        }

        $drop = array_values(array_diff(self::FLAG_KEYS, $profile['flags']));
        foreach (self::SECTION_INPUTS as $section => $inputs) {
            if (!in_array($section, $profile['sections'], true)) {
                array_push($drop, ...$inputs);
            }
        }

        if (!$profile['signs_in']) {
            array_push($drop, ...self::SIGN_IN_INPUTS);
        }

        return $drop;
    }

    /** Sign-in input, dropped for a role that doesn't sign in. */
    public const SIGN_IN_INPUTS = [
        'password', 'new_password', 'confirm_password', 'password_confirmation',
        'confirm_new_password', 'new_password_confirmation',
    ];
}
