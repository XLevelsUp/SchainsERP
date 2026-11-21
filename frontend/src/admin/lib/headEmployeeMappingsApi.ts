import type { ApiClient, ApiResponse } from '@/lib/api'
import type {
  HeadEmployeeMappingCreatePayload,
  HeadEmployeeMappingGroup,
  HeadEmployeeMappingListQuery,
  HeadEmployeeMappingUpdatePayload,
} from '@/types'
import { adminApi } from './adminApi'

const RESOURCE = '/head-employee-mappings'

function buildQuery(query: HeadEmployeeMappingListQuery): string {
  const qs = new URLSearchParams()
  if (query.employee_is_active !== undefined) {
    qs.set('employee_is_active', String(query.employee_is_active))
  }
  if (query.head_is_active !== undefined) {
    qs.set('head_is_active', String(query.head_is_active))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

// Admin console only: assigning users to heads is the admin's job (agreed
// 2026-11-20). create() and update() need the special password, so build
// them on adminApiWithSpecialPassword(...) for the save.
export function createHeadEmployeeMappingsApi(api: ApiClient) {
  return {
    // GET — grouped by employee, every head they report to nested underneath.
    // Unpaginated — the backend returns the full set in one response.
    list: (query: HeadEmployeeMappingListQuery = {}) =>
      api
        .get<ApiResponse<HeadEmployeeMappingGroup[]>>(`${RESOURCE}${buildQuery(query)}`)
        .then((r) => r.data),

    // POST — adds head_ids to employee_id's set (existing pairs skipped, not
    // duplicated). Callers should re-list afterwards rather than trust the
    // response shape to reflect the full current set.
    create: (payload: HeadEmployeeMappingCreatePayload) =>
      api.post<ApiResponse<unknown>>(RESOURCE, payload).then((r) => r.data),

    // PUT /{employee_id} — replaces the employee's whole head-set.
    update: (employeeId: number, payload: HeadEmployeeMappingUpdatePayload) =>
      api.put<ApiResponse<unknown>>(`${RESOURCE}/${employeeId}`, payload).then((r) => r.data),
  }
}

export const headEmployeeMappingsApi = createHeadEmployeeMappingsApi(adminApi)
