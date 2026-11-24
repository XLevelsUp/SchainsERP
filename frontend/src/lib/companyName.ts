import { onMounted, ref } from 'vue'
import type { ApiClient, ApiResponse } from './api'

// The company name the side menus show as a group heading (2026-11-24): the
// system setting `company_name` ("NIHAA JEWELS"), which heads can change in
// System Settings. GET /settings/company_name in the app,
// /admin/settings/company_name in the console.

export const DEFAULT_COMPANY_NAME = 'Company'

/** The setting's value as a name, or the default when it's missing or not text. */
export function companyNameFrom(value: unknown): string {
  return typeof value === 'string' && value.trim() ? value.trim() : DEFAULT_COMPANY_NAME
}

export function useCompanyName(api: ApiClient) {
  const name = ref(DEFAULT_COMPANY_NAME)
  onMounted(async () => {
    try {
      const result = await api.get<ApiResponse<{ setting_value: unknown }>>('/settings/company_name')
      name.value = companyNameFrom(result.data.setting_value)
    } catch {
      // Keeps the default; the menu still works.
    }
  })
  return name
}
