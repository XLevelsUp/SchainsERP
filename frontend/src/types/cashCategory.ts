export type CashCategoryType = 'INCOME' | 'EXPENSE' | 'AUTO_ENTRY'

export interface CashCategory {
  category_id: number
  category_name: string
  category_type: CashCategoryType | null
  is_active: boolean
  added_by: number
  created_at?: string
  updated_at?: string
}

export interface CashCategoryFormValues {
  category_name: string
  category_type: CashCategoryType | ''
  is_active: boolean
}

// GET /cash-categories doesn't use the usual { success, data: T[] } shape —
// data is a pagination envelope (PR #16's CashCategoryController::index()).
export interface CashCategoryListPage {
  total_count: number
  page_no: number
  page_size: number
  records: CashCategory[]
}
