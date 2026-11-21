import type { ApiResponse } from '@/lib/api'
import type { UserFormProfile, UserFormProfileCatalog, UserFormSection } from '@/types'
import { adminApi } from './adminApi'

// GET /api/v1/admin/user-form-profiles — which sections and permissions the
// user form shows for each role (App\Support\UserFormProfiles). The backend
// enforces the same profiles on save, so the form only ever offers what the
// save will accept.
export const userFormProfilesApi = {
  get: () =>
    adminApi.get<ApiResponse<UserFormProfileCatalog>>('/user-form-profiles').then((r) => r.data),
}

// The profile for a role id; the catalog's default (full form) for a role it
// doesn't know (e.g. created after the catalog loaded) or no role yet.
export function profileForRole(
  catalog: UserFormProfileCatalog | null,
  roleId: string | number | null | undefined,
): UserFormProfile | null {
  if (!catalog) return null
  if (roleId === null || roleId === undefined || roleId === '') return null
  return catalog.by_role_id[String(roleId)] ?? catalog.default
}

export function profileHas(profile: UserFormProfile | null, section: UserFormSection): boolean {
  return profile?.sections.includes(section) ?? false
}
