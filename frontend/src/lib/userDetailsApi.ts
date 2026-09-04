import { api, type ApiResponse } from './api'
import type { UserDetail, UserDetailFormValues, UserDetailListItem } from '@/types'

const RESOURCE = '/user-details'

// Build the exact payload the backend store() validates. Drops empty optional
// mappings and only sends fields the controller accepts.
function toPayload(form: UserDetailFormValues, includePassword = true) {
  const payload: Record<string, unknown> = {
    name: form.name,
    user_name: form.user_name,
    address: form.address,
    signature: form.signature,
    code: form.code,
    phone_no: form.phone_no,
    remarks: form.remarks || null,
    proff: form.proff,
    role_id: form.role_id,
    system_id: form.system_id,
    mailing_name: form.mailing_name,
    customer_commants: form.customer_commants || null,
    category_name: form.category_name,
    is_active: form.is_active,
    is_delete: form.is_delete,
    is_billable: form.is_billable,
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

// store()/show()/update() all wrap the user object one level deeper than
// the ApiResponse envelope: { success, data: { user, profile_image_url } }.
interface UserDetailEnvelope {
  user: UserDetail
  profile_image_url: string | null
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

  create: (form: UserDetailFormValues) =>
    api.post<ApiResponse<UserDetailEnvelope>>(RESOURCE, toPayload(form)).then((r) => r.data),

  // Frontend-ready for when the backend adds PUT /user-details/{id}.
  // Password only sent if the user typed a new one.
  update: (id: number, form: UserDetailFormValues) =>
    api
      .put<ApiResponse<UserDetailEnvelope>>(
        `${RESOURCE}/${id}`,
        toPayload(form, form.password.length > 0),
      )
      .then((r) => r.data),

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