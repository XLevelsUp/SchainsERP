import type { ApiClient, ApiResponse } from './api'
import type {
  ItemChangeResult,
  ItemConversionGroupResult,
  ItemConversionRow,
  ItemHistoryQuery,
} from '@/types'

// Item Change History and Item Conversion Group History (2026-11-25): the
// filters and request code shared by the app (/stock/reports) and the admin
// console (/admin/reports). See types/itemHistory.ts.

// Item changes: rows per page. Conversions: item pairs (groups) per page,
// each with all its conversions.
export const ITEM_CHANGE_PAGE_SIZE = 100
export const ITEM_CONVERSION_GROUP_PAGE_SIZE = 25

/** The page's filters. `head_id` is used by the admin console only. */
export interface ItemHistoryFilterValues {
  head_id: number | null
  item_id: number | null
  from_date: string
  to_date: string
}

/**
 * What both pages open with (agreed 2026-11-25): the current month, the 1st
 * to today. Clear resets to it. (The endpoints alone default to today.)
 */
export function defaultItemHistoryFilters(today: Date = new Date()): ItemHistoryFilterValues {
  const pad = (n: number) => String(n).padStart(2, '0')
  const month = `${today.getFullYear()}-${pad(today.getMonth() + 1)}`
  return { head_id: null, item_id: null, from_date: `${month}-01`, to_date: `${month}-${pad(today.getDate())}` }
}

export function itemHistoryQuery(
  filters: ItemHistoryFilterValues,
  options: { withHead: boolean; pageNo?: number; pageSize: number },
): ItemHistoryQuery {
  const query: ItemHistoryQuery = {
    from_date: filters.from_date || undefined,
    // Left out, the backend uses from_date: one day.
    to_date: filters.to_date || undefined,
    item_id: filters.item_id ?? undefined,
    page_no: options.pageNo ?? 1,
    page_size: options.pageSize,
  }
  if (options.withHead && filters.head_id !== null) query.head_id = filters.head_id
  return query
}

function buildQuery(params: ItemHistoryQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

/** The two endpoints under `prefix` ('/stock/reports' or the console's '/reports'). */
export function createItemHistoryApi(client: ApiClient, prefix: string) {
  return {
    getItemChanges: (params: ItemHistoryQuery) =>
      client
        .get<ApiResponse<ItemChangeResult>>(`${prefix}/item-changes${buildQuery(params)}`)
        .then((r) => r.data),
    getConversionGroups: (params: ItemHistoryQuery) =>
      client
        .get<ApiResponse<ItemConversionGroupResult>>(`${prefix}/item-conversion-groups${buildQuery(params)}`)
        .then((r) => r.data),
  }
}

/** Weights and touches as the other stock reports show them: up to 4 places, "—" for none. */
export function formatWeight(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 4 }) : '—'
}

/** "Copper 10% · 1.2 g, Silver 5% · 0.6 g", or "—" without alloys. */
export function alloySummary(row: Pick<ItemConversionRow, 'alloys'>): string {
  if (!row.alloys.length) return '—'
  return row.alloys
    .map((a) => `${a.alloy_item_name ?? `Item #${a.alloy_item_id}`} ${formatWeight(a.alloy_percentage)}% · ${formatWeight(a.alloy_grams)} g`)
    .join(', ')
}
