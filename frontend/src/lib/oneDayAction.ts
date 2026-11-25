import { cashMethodLabel, cashTypeBadge } from './cashTxnTypes'
import type {
  OneDayActionCashRow,
  OneDayActionQuery,
  OneDayActionSectionName,
  OneDayActionStockRow,
} from '@/types'

// One Day Action (2026-11-25): the legacy screen's filters and its single
// Export, shared by the app and the admin console.

export const ONE_DAY_ACTION_PAGE_SIZE = 500

/** The legacy screen's filters. `head_id` is used by the admin console only. */
export interface OneDayActionFilterValues {
  head_id: number | null
  employee_id: number | null
  item_id: number | null
  type: 'IN' | 'OUT' | null
  given_by: number | null
  given_to: number | null
  role_id: number | null
  from_date: string
  to_date: string
}

export function oneDayActionQuery(
  filters: OneDayActionFilterValues,
  options: { withHead: boolean; section?: OneDayActionSectionName; pageNo?: number },
): OneDayActionQuery {
  const query: OneDayActionQuery = {
    from_date: filters.from_date || undefined,
    // Left out, the backend uses from_date: one day.
    to_date: filters.to_date || undefined,
    employee_id: filters.employee_id ?? undefined,
    item_id: filters.item_id ?? undefined,
    type: filters.type ?? undefined,
    given_by: filters.given_by ?? undefined,
    given_to: filters.given_to ?? undefined,
    role_id: filters.role_id ?? undefined,
    section: options.section,
    page_no: options.pageNo ?? 1,
    page_size: ONE_DAY_ACTION_PAGE_SIZE,
  }
  if (options.withHead && filters.head_id !== null) query.head_id = filters.head_id
  return query
}

const CSV_HEADER = [
  'Section',
  'ID',
  'Date',
  'Flow',
  'Type',
  'Given By',
  'Given To',
  'Item',
  'Category',
  'Grams',
  'Touch',
  'Purity',
  'Wastage %',
  'Wastage Grams',
  'Amount',
  'Method',
  'Remarks',
  'Entered By (user id)',
]

function csvCell(value: unknown): string {
  return `"${String(value ?? '').replace(/"/g, '""')}"`
}

/** One file, both sections; the Section column says which (STOCK / CASH). */
export function oneDayActionCsv(stock: OneDayActionStockRow[], cash: OneDayActionCashRow[]): string {
  const stockLines = stock.map((r) => [
    'STOCK', r.id, r.added_at, r.stock_type, r.entry_type, r.given_by, r.given_to, r.item_name, '',
    r.grams, r.touch, r.purity, r.waste_total, r.waste_value, '', '', r.remarks, r.added_by,
  ])
  const cashLines = cash.map((r) => [
    'CASH', r.id, r.added_at, r.flow, cashTypeBadge(r.type).label, r.given_by, r.given_to, '', r.category_name,
    '', '', '', '', '', r.amount, cashMethodLabel(r.payment_method, r.bank_name), r.remarks, r.added_by,
  ])
  return [CSV_HEADER, ...stockLines, ...cashLines].map((line) => line.map(csvCell).join(',')).join('\n')
}
