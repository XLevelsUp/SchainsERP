import { api, type ApiResponse } from './api'
import { buildMultipartForm } from './multipartForm'
import { USER_FEATURE_FLAG_KEYS } from './userFeatureFlags'
import type { UserDetail, UserDetailFormValues, UserDetailListItem } from '@/types'

const RESOURCE = '/user-details'

// store() still validates signature/code/proff/mailing_name/system_id as
// required (and all five columns are NOT NULL), but the Users forms no
// longer ask for code/system_id/proof-as-text and leave signature and
// mailing name optional. Blanks get the same placeholders
// PhoneBookController::store() and AddRetailerModal already use — '-' for
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
    address: form.address,
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

  return payload
}

// JSON unless a proof image is attached — store()/update() only accept
// aadhar_image as a real file, which needs multipart. Nulls go as '' so they
// still clear their column: buildMultipartForm drops nulls entirely, and
// Laravel's ConvertEmptyStringsToNull turns '' back into null server-side.
function toMultipart(payload: Record<string, unknown>, image: File, extra: Record<string, string> = {}) {
  const fields = Object.fromEntries(
    Object.entries(payload).map(([key, value]) => [key, value === null ? '' : value]),
  )
  return buildMultipartForm({ ...fields, ...extra, aadhar_image: image })
}

// store()/show()/update() all wrap the user object one level deeper than
// the ApiResponse envelope: { success, data: { user, profile_image_url } }.
interface UserDetailEnvelope {
  user: UserDetail
  profile_image_url: string | null
  aadhar_image_url: string | null
}

// PUT /user-details/{id}/update-cc echoes back only the two fields it
// touched, not the whole user record.
interface CustomerCommentsResult {
  user_id: number
  customer_commants: string | null
}

export const userDetailsApi = {
  // Flattened summary shape (PR #15) — fine for pickers/tables, not for
  // editing. Pass ?type= to filter by role (HEAD/EMPLOYEE/CUSTOMER/...).
  // Pass ?module= to pick which extra fields come back: 'stock' adds
  // gm/purity, 'cash' adds hand_cash/rtgs_cash — see UserDetailListItem.
  list: (type?: string, module?: 'stock' | 'cash') => {
    const params = new URLSearchParams()
    if (type) params.set('type', type)
    if (module) params.set('module', module)
    const qs = params.toString()
    return api
      .get<ApiResponse<UserDetailListItem[]>>(qs ? `${RESOURCE}?${qs}` : RESOURCE)
      .then((r) => r.data)
  },

  get: (id: number) =>
    api.get<ApiResponse<UserDetailEnvelope>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (form: UserDetailFormValues) => {
    const payload = toPayload(form)
    return (
      form.aadhar_image
        ? api.postForm<ApiResponse<UserDetailEnvelope>>(
            RESOURCE,
            toMultipart(payload, form.aadhar_image),
          )
        : api.post<ApiResponse<UserDetailEnvelope>>(RESOURCE, payload)
    ).then((r) => r.data)
  },

  // Frontend-ready for when the backend adds PUT /user-details/{id}.
  // Password only sent if the user typed a new one. With a new proof image
  // the request goes as POST + _method=PUT: PHP only parses multipart bodies
  // on POST, and Laravel's method override still routes it to update().
  update: (id: number, form: UserDetailFormValues) => {
    const payload = toPayload(form, form.password.length > 0)
    return (
      form.aadhar_image
        ? api.postForm<ApiResponse<UserDetailEnvelope>>(
            `${RESOURCE}/${id}`,
            toMultipart(payload, form.aadhar_image, { _method: 'PUT' }),
          )
        : api.put<ApiResponse<UserDetailEnvelope>>(`${RESOURCE}/${id}`, payload)
    ).then((r) => r.data)
  },

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),

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