import { api as appApi, type ApiClient, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import { USER_FEATURE_FLAG_KEYS } from './userFeatureFlags'
import type {
  OpeningBalanceInput,
  OpeningBalanceResult,
  UserDetail,
  UserDetailFormValues,
  UserDetailListItem,
} from '@/types'

const RESOURCE = '/user-details'

// store() still validates signature/code/proff/mailing_name/system_id as
// required (and all five columns are NOT NULL), but the Users forms no
// longer ask for code/system_id/proof-as-text and leave signature and
// mailing name optional. Blanks get the same placeholders
// AddRetailerModal uses — '-' for
// free text, the user's own name for mailing name, and a stamped value for
// system_id because it is unique:user_details. On edit the record's stored
// values are loaded into the form, so these only apply to fields left blank.
function newSystemId() {
  return `USER-${Date.now()}${Math.floor(Math.random() * 90 + 10)}`
}

// Build the exact payload the backend store() validates. Drops empty optional
// mappings and only sends fields the controller accepts.
function toPayload(form: UserDetailFormValues, includePassword = true) {
  const payload: Record<string, unknown> = {
    name: form.name,
    user_name: form.user_name,
    // store() requires it; a role whose form has no address (2026-11-21
    // user-form profiles) sends the same '-' placeholder as the fields below.
    address: form.address.trim() || '-',
    signature: form.signature.trim() || '-',
    code: form.code.trim() || '-',
    phone_no: form.phone_no,
    remarks: form.remarks || null,
    proff: form.proff.trim() || '-',
    role_id: form.role_id,
    system_id: form.system_id.trim() || newSystemId(),
    mailing_name: form.mailing_name.trim() || form.name,
    customer_commants: form.customer_commants || null,
    category_name: form.category_name,
    is_active: form.is_active,
    is_delete: form.is_delete,
    is_billable: form.is_billable,
  }

  // Bug fix (2026-09-26): this function used to stop at is_billable, so
  // none of the 40 per-user feature flags UsersView.vue collects (PR
  // #43–#45's "Permissions & features" section) ever left the browser —
  // the checkboxes toggled local state and toPayload() silently dropped
  // all of it before the request went out. store()/update() both validate
  // every key in USER_FEATURE_FLAG_KEYS as `sometimes|boolean`, so they
  // belong here same as is_active/is_delete/is_billable above.
  for (const key of USER_FEATURE_FLAG_KEYS) {
    payload[key] = form[key]
  }

  if (includePassword) payload.password = form.password

  // No per-row `id`, on purpose. Since PR #53 update() updates a row in
  // place when it carries an id, but that branch writes an `updated_at`
  // column users_items_mappings doesn't have, so the whole save would fail
  // (BACKEND_TODO §10). Without ids update() deletes and re-creates the rows.
  const itemMappings = form.item_mappings
    .filter((m) => m.item_id !== null)
    .map((m) => ({
      item_id: m.item_id,
      item_grams_total: m.item_grams_total || '0',
      item_purity_total: m.item_purity_total || '0',
      is_primary: m.is_primary,
    }))
  if (itemMappings.length) payload.item_mappings = itemMappings

  const headMappings = form.head_mappings
    .filter((m) => m.head_id !== null)
    .map((m) => ({ head_id: m.head_id }))
  if (headMappings.length) payload.head_mappings = headMappings

  const cashHeadMappings = form.cash_head_mappings
    .filter((m) => m.head_id !== null)
    .map((m) => ({ head_id: m.head_id }))
  if (cashHeadMappings.length) payload.cash_head_mappings = cashHeadMappings

  // 2026-11-21 sections, sent only by forms that carry them (the admin
  // console). Customer touches go as the whole set — update() replaces it.
  if (form.joining_date !== undefined) payload.joining_date = form.joining_date || null
  // HEAD form (2026-11-21).
  if (form.aadhar_number !== undefined) payload.aadhar_number = form.aadhar_number.trim() || null
  if (form.per_day_salary !== undefined) payload.per_day_salary = form.per_day_salary === '' ? null : form.per_day_salary
  // SALARY form (2026-11-23).
  if (form.additional_phone_no !== undefined) payload.additional_phone_no = form.additional_phone_no.trim() || null
  // A new user's opening balances (2026-11-24); only when there are some,
  // since sending the key at all asks for the special password.
  if (form.opening_balances?.length) {
    payload.opening_balances = form.opening_balances.map((e) => ({
      field: e.field,
      item_id: e.item_id,
      value: e.value,
      remarks: e.remarks.trim() || null,
    }))
  }
  if (form.report_password) payload.report_password = form.report_password
  if (form.head_user_mappings?.length) {
    const headUsers = employeePayload(form.head_user_mappings)
    if (headUsers.length) payload.head_user_mappings = headUsers
  }
  if (form.customer_touch_mappings !== undefined) {
    payload.customer_touch_mappings = form.customer_touch_mappings
      .filter((m) => m.customer_touch_id !== null)
      .map((m) => ({ customer_touch_id: m.customer_touch_id, is_active: m.is_active }))
  }

  return payload
}

function headPayload(rows: UserDetailFormValues['head_mappings']) {
  return rows.filter((m) => m.head_id !== null).map((m) => ({ head_id: m.head_id }))
}

function employeePayload(rows: NonNullable<UserDetailFormValues['head_user_mappings']>) {
  return rows.filter((m) => m.employee_id !== null).map((m) => ({ employee_id: m.employee_id }))
}

// The image fields store()/update() accept as real files.
function filesOf(form: UserDetailFormValues): Record<string, File> {
  const files: Record<string, File> = {}
  if (form.aadhar_image) files.aadhar_image = form.aadhar_image
  if (form.profile_image) files.profile_image = form.profile_image
  if (form.aadhar_back_image) files.aadhar_back_image = form.aadhar_back_image
  if (form.image_file) files.image_url = form.image_file
  return files
}

// Lists update() replaces as a whole set. FormData can't carry an empty
// list, so one that's empty goes as '' — Laravel turns it into null, which
// update() reads as "none". (item_mappings isn't one of them: an empty one
// still can't go multipart, see update() below.)
const REPLACED_LISTS = ['head_mappings', 'cash_head_mappings', 'head_user_mappings', 'customer_touch_mappings']

// JSON unless an image is attached — store()/update() only accept
// aadhar_image / profile_image as real files, which needs multipart. Nulls go
// as '' so they still clear their column: buildMultipartForm drops nulls
// entirely, and Laravel's ConvertEmptyStringsToNull turns '' back into null
// server-side.
function toMultipart(
  payload: Record<string, unknown>,
  files: Record<string, File>,
  extra: Record<string, string> = {},
) {
  const fields = Object.fromEntries(
    Object.entries(payload).map(([key, value]) => {
      if (value === null) return [key, '']
      if (REPLACED_LISTS.includes(key) && Array.isArray(value) && value.length === 0) return [key, '']
      return [key, value]
    }),
  )
  return buildMultipartForm({ ...fields, ...extra, ...files })
}

// store()/show()/update() all wrap the user object one level deeper than
// the ApiResponse envelope: { success, data: { user, profile_image_url } }.
interface UserDetailEnvelope {
  user: UserDetail
  profile_image_url: string | null
  aadhar_image_url: string | null
  // show() only (2026-11-21): the HEAD form's Image and Aadhar back.
  image_url?: string | null
  aadhar_back_image_url?: string | null
}

// PUT /user-details/{id}/update-cc echoes back only the two fields it
// touched, not the whole user record.
interface CustomerCommentsResult {
  user_id: number
  customer_commants: string | null
}

// Written against ApiClient so the admin console reuses it with its own
// client (admin/lib/resources.ts). `userDetailsApi` below is the app's.
export function createUserDetailsApi(api: ApiClient) {
  return {
    // Flattened summary shape (PR #15) — fine for pickers/tables, not for
    // editing. Pass ?type= to filter by role (HEAD/EMPLOYEE/CUSTOMER/...).
    // Pass ?module= to pick which extra fields come back: 'stock' adds
    // gm/purity, 'cash' adds hand_cash/rtgs_cash — see UserDetailListItem.
    // mappedOnly: only the signed-in user and the users mapped to them
    // (head_employee_mappings, or the cash ones for module=cash), whatever
    // their role — the Stock screen's user pickers.
    list: (type?: string, module?: 'stock' | 'cash', options: { mappedOnly?: boolean } = {}) => {
      const params = new URLSearchParams()
      if (type) params.set('type', type)
      if (module) params.set('module', module)
      if (options.mappedOnly) params.set('mapped_only', '1')
      const qs = params.toString()
      return api
        .get<ApiResponse<UserDetailListItem[]>>(qs ? `${RESOURCE}?${qs}` : RESOURCE)
        .then((r) => r.data)
    },

    get: (id: number) =>
      api.get<ApiResponse<UserDetailEnvelope>>(`${RESOURCE}/${id}`).then((r) => r.data),

    create: (form: UserDetailFormValues) => {
      const payload = toPayload(form)
      const files = filesOf(form)
      return (
        Object.keys(files).length
          ? api.postForm<ApiResponse<UserDetailEnvelope>>(RESOURCE, toMultipart(payload, files))
          : api.post<ApiResponse<UserDetailEnvelope>>(RESOURCE, payload)
      ).then((r) => r.data)
    },

    // PUT /user-details/{id} (admin console). Password only sent if the user
    // typed a new one. With a new image the request goes as POST +
    // _method=PUT: PHP only parses multipart bodies on POST, and Laravel's
    // method override still routes it to update().
    //
    // includeItemMappings: false leaves item_mappings out entirely.
    // includeHeadMappings: true sends the head lists — stock and cash heads,
    // and for a head the users under them — even empty, so update() replaces
    // them. Only when they changed: sending them
    // needs the special password (RequireSpecialPassword), so the caller
    // passes a client that carries it.
    update: (
      id: number,
      form: UserDetailFormValues,
      options: { includeItemMappings?: boolean; includeHeadMappings?: boolean } = {},
    ) => {
      const payload = toPayload(form, form.password.length > 0)
      // An existing user's opening balances go one at a time (openingBalance).
      delete payload.opening_balances
      const files = filesOf(form)
      const multipart = Object.keys(files).length > 0

      if (options.includeHeadMappings) {
        payload.head_mappings = headPayload(form.head_mappings)
        payload.cash_head_mappings = headPayload(form.cash_head_mappings)
        payload.head_user_mappings = employeePayload(form.head_user_mappings ?? [])
      } else {
        delete payload.head_mappings
        delete payload.cash_head_mappings
        delete payload.head_user_mappings
      }

      if (options.includeItemMappings === false) {
        delete payload.item_mappings
      } else if (!multipart && payload.item_mappings === undefined) {
        // update() syncs item mappings only when the key is present (PR #53),
        // and toPayload() leaves an empty list out, so removing a user's last
        // item never reached the server. Send [] so it clears. JSON only:
        // FormData can't carry an empty array, and the '' fallback reaches
        // update() as null, which crashes its array_column() (BACKEND_TODO §10).
        payload.item_mappings = []
      }
      return (
        multipart
          ? api.postForm<ApiResponse<UserDetailEnvelope>>(
              `${RESOURCE}/${id}`,
              toMultipart(payload, files, { _method: 'PUT' }),
            )
          : api.put<ApiResponse<UserDetailEnvelope>>(`${RESOURCE}/${id}`, payload)
      ).then((r) => r.data)
    },

    remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),

    // POST /user-details/{id}/opening-balance (admin console, 2026-11-24).
    // Needs the special password: build this on adminApiWithSpecialPassword.
    openingBalance: (id: number, entry: OpeningBalanceInput) =>
      api
        .post<ApiResponse<OpeningBalanceResult>>(`${RESOURCE}/${id}/opening-balance`, {
          field: entry.field,
          item_id: entry.item_id,
          value: entry.value,
          remarks: entry.remarks.trim() || null,
        })
        .then((r) => r.data),

    // PATCH /user-details/{id}/status (admin console): the Users list's
    // active toggle, without sending the whole form.
    setStatus: (id: number, isActive: boolean) =>
      api
        .put<ApiResponse<{ user_id: number; is_active: boolean }>>(`${RESOURCE}/${id}/status`, {
          is_active: isActive,
        })
        .then((r) => r.data),

    // PUT /user-details/{id}/update-cc (UserDetailController::updateCc, added
    // in PR #32) — a dedicated single-field endpoint so the customer comments
    // box can be saved on its own, without round-tripping the whole user
    // record through update()'s much larger validated payload.
    // UpdateCcRequest caps the text at 1500 characters and allows null.
    updateCustomerComments: (id: number, customerComments: string | null) =>
      api
        .put<ApiResponse<CustomerCommentsResult>>(`${RESOURCE}/${id}/update-cc`, {
          customer_commants: customerComments,
        })
        .then((r) => r.data),
  }
}

export const userDetailsApi = createUserDetailsApi(appApi)
