// RoleController. Roles are created and viewed in the admin console; the
// app reads them for its Role pickers.
export interface Role {
  id: number
  // Stored in capitals and unique ignoring case (RoleController).
  role: string
  added_at?: string
  touch: number | null
  // BACKEND_TODO §14, fixed 2026-11-20: the column exists and every response
  // carries it. Inactive roles drop out of the pickers for new assignments.
  // Still read through isRoleActive(), which treats a missing value as active.
  is_active?: boolean
}

export interface RoleFormValues {
  role: string
  touch: number | null
  is_active: boolean
}
