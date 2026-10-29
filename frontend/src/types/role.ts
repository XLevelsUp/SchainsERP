export interface Role {
  id: number
  role: string
  added_at?: string
  touch: number | null
  // Optional because the backend does not return it yet: the `roles` table
  // has no status column and RoleController validates only role/added_at/
  // touch (BACKEND_TODO §14). Read it through isRoleActive(), which treats a
  // missing value as active so nothing is hidden until the column ships.
  is_active?: boolean
}

export interface RoleFormValues {
  role: string
  touch: number | null
  is_active: boolean
}