import { api, type ApiResponse } from './api'
import type { CashCategory, CashCategoryFormValues, CashCategoryListPage } from '@/types'

const RESOURCE = '/cash-categories'

function toPayload(form: CashCategoryFormValues) {
  return {
    category_name: form.category_name,
    category_type: form.category_type || null,
    is_active: form.is_active,
  }
}

export const cashCategoriesApi = {
  // index() returns a pagination envelope, not a bare array — see
  // CashCategoryListPage. limit defaults to 50 server-side; passed
  // explicitly high here since callers just want "all active categories"
  // for a dropdown, not a paged table.
  list: (limit = 200) =>
    api
      .get<ApiResponse<CashCategoryListPage>>(`${RESOURCE}?limit=${limit}`)
      .then((r) => r.data.records),

  get: (id: number) => api.get<ApiResponse<CashCategory>>(`${RESOURCE}/${id}`).then((r) => r.data),

  create: (form: CashCategoryFormValues) =>
    api.post<ApiResponse<CashCategory>>(RESOURCE, toPayload(form)).then((r) => r.data),

  update: (id: number, form: Partial<CashCategoryFormValues>) =>
    api
      .put<ApiResponse<CashCategory>>(`${RESOURCE}/${id}`, {
        ...(form.category_name !== undefined ? { category_name: form.category_name } : {}),
        ...(form.category_type !== undefined ? { category_type: form.category_type || null } : {}),
        ...(form.is_active !== undefined ? { is_active: form.is_active } : {}),
      })
      .then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`),
}
