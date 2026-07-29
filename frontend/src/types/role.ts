export interface Role {
  id: number
  role: string
  added_at?: string
  touch: number | null
}

export interface RoleFormValues {
  role: string
  touch: number | null
}