// /head-employee-mappings — HeadEmployeeMappingController (PR #34, rewritten
// to a bulk, grouped-by-employee shape; there is no per-mapping show/destroy
// any more, only a grouped index, a bulk store, and a per-employee update
// that replaces the whole head-set).

export interface HeadEmployeeMappingHead {
  mapping_id: number
  head_id: number
  head_name: string | null
  added_at: string
}

// GET /head-employee-mappings — one row per employee, every head they
// report to nested underneath.
export interface HeadEmployeeMappingGroup {
  employee_id: number
  employee_name: string | null
  heads: HeadEmployeeMappingHead[]
}

export interface HeadEmployeeMappingListQuery {
  employee_is_active?: boolean
  head_is_active?: boolean
}

// POST /head-employee-mappings — adds head_ids to employee_id's set.
// Duplicates (already-mapped pairs) are silently skipped, so the response
// only contains the rows actually inserted — callers should re-list rather
// than assume this array covers every requested head_id.
export interface HeadEmployeeMappingCreatePayload {
  employee_id: number
  head_ids: number[]
}

// PUT /head-employee-mappings/{employee_id} — REPLACES the employee's whole
// head-set: anything not in head_ids is deleted, anything new is inserted.
export interface HeadEmployeeMappingUpdatePayload {
  head_ids: number[]
}
