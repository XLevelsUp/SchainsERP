import { api, type ApiResponse } from './api'
import type { UserDetail, UserDetailFormValues } from '@/types'

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

export const userDetailsApi = {
  list: () => api.get<ApiResponse<UserDetail[]>>(RESOURCE).then((r) => r.data),

  get: (id: number) => api.get<ApiResponse<UserDetail>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (form: UserDetailFormValues) =>
    api.post<ApiResponse<UserDetail>>(RESOURCE, toPayload(form)).then((r) => r.data),

  // Frontend-ready for when the backend adds PUT /user-details/{id}.
  // Password only sent if the user typed a new one.
  update: (id: number, form: UserDetailFormValues) =>
    api
      .put<ApiResponse<UserDetail>>(`${RESOURCE}/${id}`, toPayload(form, form.password.length > 0))
      .then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}