import { emptyUserFeatureFlags, USER_FEATURE_FLAG_GROUPS, USER_FEATURE_FLAG_KEYS } from '@/lib/userFeatureFlags'
import type {
  HeadMappingInput,
  Item,
  UserDetail,
  UserDetailFormValues,
  UserFeatureFlagKey,
  UserFormProfile,
  UserFormSection,
} from '@/types'

// Helpers for the admin console's role-driven user form
// (admin/components/users/UserFormCard.vue).

export function makeEmptyUserForm(): UserDetailFormValues {
  return {
    name: '',
    user_name: '',
    password: '',
    address: '',
    signature: '',
    code: '',
    phone_no: '',
    remarks: '',
    proff: '',
    role_id: '',
    system_id: '',
    mailing_name: '',
    customer_commants: '',
    // No default — the admin picks one (see lib/userCategory.ts).
    category_name: null,
    is_active: true,
    is_delete: false,
    is_billable: false,
    ...emptyUserFeatureFlags(),
    item_mappings: [],
    head_mappings: [],
    cash_head_mappings: [],
    aadhar_image: null,
    joining_date: '',
    customer_touch_mappings: [],
    profile_image: null,
    head_user_mappings: [],
    aadhar_number: '',
    aadhar_back_image: null,
    image_file: null,
    per_day_salary: '',
    report_password: '',
    additional_phone_no: '',
  }
}

// A fetched user (GET /user-details/{id}) in the form's shape. code /
// system_id / proff have no inputs, but are loaded so update() writes the
// stored values back unchanged rather than toPayload()'s placeholders.
export function userToForm(user: UserDetail): UserDetailFormValues {
  return {
    ...makeEmptyUserForm(),
    name: user.name,
    user_name: user.user_name,
    address: user.address ?? '',
    signature: user.signature ?? '',
    code: user.code ?? '',
    phone_no: user.phone_no,
    remarks: user.remarks ?? '',
    proff: user.proff ?? '',
    // role_id comes back as a number; the Role options are String(role.id).
    role_id: String(user.role_id),
    system_id: user.system_id ?? '',
    mailing_name: user.mailing_name ?? '',
    customer_commants: user.customer_commants ?? '',
    category_name: user.category_name,
    is_active: user.is_active,
    is_delete: user.is_delete,
    is_billable: user.is_billable,
    // Start from all-false and overlay what the response carries, so a column
    // the API omits stays false rather than undefined.
    ...emptyUserFeatureFlags(),
    ...Object.fromEntries(
      USER_FEATURE_FLAG_KEYS.filter((k) => user[k] !== undefined && user[k] !== null).map((k) => [
        k,
        Boolean(user[k]),
      ]),
    ),
    item_mappings: (user.item_mappings ?? []).map((m) => ({
      item_id: m.item_id,
      item_grams_total: m.item_grams_total ?? '0',
      item_purity_total: m.item_purity_total ?? '0',
      is_primary: m.is_primary ? 1 : 0,
    })),
    head_mappings: (user.head_employee_mappings ?? []).map((m) => ({ head_id: m.head_id })),
    cash_head_mappings: (user.cash_head_employee_mappings ?? []).map((m) => ({ head_id: m.head_id })),
    joining_date: user.joining_date ?? '',
    customer_touch_mappings: (user.customer_touch_mappings ?? []).map((m) => ({
      customer_touch_id: m.customer_touch_id,
      is_active: Boolean(m.is_active),
    })),
    head_user_mappings: (user.head_user_mappings ?? []).map((m) => ({ employee_id: m.employee_id })),
    aadhar_number: user.aadhar_number ?? '',
    per_day_salary:
      user.per_day_salary === null || user.per_day_salary === undefined ? '' : String(user.per_day_salary),
    additional_phone_no: user.additional_phone_no ?? '',
  }
}

// Employee ids of a head-users list, sorted, blanks dropped.
export function employeeIdsOf(rows: { employee_id: number | null }[]): number[] {
  return rows
    .map((m) => m.employee_id)
    .filter((id): id is number => id !== null)
    .sort((a, b) => a - b)
}

// Head ids of a mapping list, sorted, blanks dropped — for spotting a change.
export function headIdsOf(rows: HeadMappingInput[]): number[] {
  return rows
    .map((m) => m.head_id)
    .filter((id): id is number => id !== null)
    .sort((a, b) => a - b)
}

export function sameIds(a: number[], b: number[]): boolean {
  return a.length === b.length && a.every((id, i) => id === b[i])
}

// Today as YYYY-MM-DD in local time (the joining-date input's format).
export function todayIso(now = new Date()): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

/**
 * Applies a role's defaults to a new user's form when the role is picked or
 * changed (create only; editing never applies them). `previous` is the
 * profile of the role picked before, so a value that was only that role's
 * default follows the new role instead of sticking:
 *  - category: set when empty or still the previous role's default;
 *  - default permissions: the previous role's are switched off, the new
 *    role's on (SALARY: salary person = YES);
 *  - joining date: today, when the role asks for it and none is set;
 *  - primary item: added when the role names one, it exists, and no items
 *    are listed yet.
 */
export function applyRoleDefaults(
  form: UserDetailFormValues,
  next: UserFormProfile,
  previous: UserFormProfile | null,
  items: Pick<Item, 'item_id' | 'item_name'>[],
  today: string = todayIso(),
): void {
  if (next.default_category && (!form.category_name || form.category_name === previous?.default_category)) {
    form.category_name = next.default_category
  }

  for (const flag of previous?.default_flags ?? []) {
    if (!next.default_flags.includes(flag)) form[flag] = false
  }
  for (const flag of next.default_flags) form[flag] = true

  if (next.joining_date_defaults_to_today && next.sections.includes('joining_date') && !form.joining_date) {
    form.joining_date = today
  }

  addDefaultPrimaryItem(form, next, items)
}

/**
 * Adds the role's primary item (GOLD), grams and purity 0, when the role
 * names one, it exists, and no items are listed yet. Also used when the
 * items list arrives after the role was picked.
 */
export function addDefaultPrimaryItem(
  form: UserDetailFormValues,
  profile: UserFormProfile,
  items: Pick<Item, 'item_id' | 'item_name'>[],
): void {
  const itemName = profile.default_primary_item?.trim().toUpperCase()
  if (!itemName || !profile.sections.includes('items') || form.item_mappings.length) return
  const item = items.find((i) => i.item_name.trim().toUpperCase() === itemName)
  if (item) {
    form.item_mappings.push({ item_id: item.item_id, item_grams_total: '0', item_purity_total: '0', is_primary: 1 })
  }
}

// Every section, for when the profiles couldn't be loaded: the full form,
// which is what a role without its own profile gets anyway. The backend
// still enforces the real profile on save.
export const ALL_USER_FORM_SECTIONS: UserFormSection[] = [
  'contact',
  'additional_phone',
  'remarks',
  'customer_comments',
  'heads',
  'head_users',
  'cash_heads',
  'items',
  'balances',
  'customer_touches',
  'image',
  'profile_image',
  'proof',
  'salary',
  'joining_date',
  'report_password',
  'account',
  'flags',
]

export const FULL_USER_FORM_PROFILE: UserFormProfile = {
  role: null,
  is_default: true,
  lock_role_on_edit: false,
  signs_in: true,
  default_category: null,
  default_primary_item: null,
  default_flags: [],
  joining_date_defaults_to_today: false,
  sections: ALL_USER_FORM_SECTIONS,
  flags: USER_FEATURE_FLAG_KEYS,
}

const FLAG_LABELS = new Map(
  USER_FEATURE_FLAG_GROUPS.flatMap((g) => g.flags.map((f) => [f.key, f.label] as const)),
)

export function flagLabel(key: UserFeatureFlagKey): string {
  return FLAG_LABELS.get(key) ?? key
}

// The catalogue's groups, cut down to the flags a profile allows.
export function flagGroupsFor(flags: UserFeatureFlagKey[]) {
  const allowed = new Set(flags)
  return USER_FEATURE_FLAG_GROUPS.map((g) => ({
    ...g,
    flags: g.flags.filter((f) => allowed.has(f.key)),
  })).filter((g) => g.flags.length > 0)
}
