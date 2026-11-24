import { api as appApi, type ApiClient, type ApiResponse } from './api'
import type { PhoneBookContact, PhoneBookFormValues, PhoneBookPage, PhoneBookQuery } from '@/types'

const RESOURCE = '/phone-book'

// The phone book (2026-11-24): its own list of contacts (types/phoneBook.ts).
// Written against ApiClient so the admin console reuses it with its own
// client (admin/lib/resources.ts). The app's instance below only reads: the
// app has no write routes, so create/update/remove exist for the console.

function buildQuery(params: PhoneBookQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    qs.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export function createPhoneBookApi(api: ApiClient) {
  return {
    // Paginated; the envelope's `data` is the paginator, rows in `data.data`.
    list: (params: PhoneBookQuery = {}) =>
      api.get<ApiResponse<PhoneBookPage>>(`${RESOURCE}${buildQuery(params)}`).then((r) => r.data),

    // Every contact matching the filters, in one request.
    listAll: (params: Omit<PhoneBookQuery, 'all' | 'page' | 'per_page'> = {}) =>
      api
        .get<ApiResponse<PhoneBookPage>>(`${RESOURCE}${buildQuery({ ...params, all: true })}`)
        .then((r) => r.data.data),

    get: (id: number) => api.get<ApiResponse<PhoneBookContact>>(`${RESOURCE}/${id}`).then((r) => r.data),

    create: (values: PhoneBookFormValues) =>
      api.post<ApiResponse<PhoneBookContact>>(RESOURCE, toPayload(values)).then((r) => r.data),

    update: (id: number, values: PhoneBookFormValues) =>
      api.put<ApiResponse<PhoneBookContact>>(`${RESOURCE}/${id}`, toPayload(values)).then((r) => r.data),

    // Only the status, so a contact whose copied number isn't 10 digits yet
    // can still be switched on or off.
    setActive: (id: number, isActive: boolean) =>
      api.put<ApiResponse<PhoneBookContact>>(`${RESOURCE}/${id}`, { is_active: isActive }).then((r) => r.data),

    remove: (id: number) => api.delete<ApiResponse<{ id: number }>>(`${RESOURCE}/${id}`),
  }
}

function toPayload(values: PhoneBookFormValues) {
  return {
    name: values.name.trim(),
    phone_no: values.phone_no.trim(),
    role_id: values.role_id,
    is_active: values.is_active,
  }
}

export const phoneBookApi = createPhoneBookApi(appApi)
