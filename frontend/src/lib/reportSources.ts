import type {
  ConsolidatedQuery,
  ConsolidatedResult,
  IdWiseReportResult,
  Item,
  ItemChangeResult,
  ItemConversionGroupResult,
  ItemHistoryQuery,
  ItemsObcbQuery,
  ItemsObcbResult,
  OneDayActionQuery,
  OneDayActionResult,
  Role,
  UserDetailListItem,
} from '@/types'

// Where a report screen gets its data (2026-11-24). The app's screens use
// their own defaults (the signed-in head); the admin console passes its own
// source, built on /admin/reports, plus the heads to report on.

/** A head the admin console reports on (one section each). */
export interface ReportHead {
  id: number
  name: string
}

interface Lookups {
  loadUsers: () => Promise<UserDetailListItem[]>
  loadItems: () => Promise<Item[]>
}

export interface ItemsObcbSource extends Lookups {
  fetch: (query: ItemsObcbQuery) => Promise<ItemsObcbResult>
  exportCsv: (query: Omit<ItemsObcbQuery, 'page_no' | 'page_size'>) => Promise<void>
}

export interface ConsolidatedSource extends Lookups {
  fetch: (query: ConsolidatedQuery) => Promise<ConsolidatedResult>
  exportCsv: (query: Omit<ConsolidatedQuery, 'page_no_out' | 'page_no_in' | 'page_size'>) => Promise<void>
}

export interface OneDayActionSource extends Lookups {
  fetch: (query: OneDayActionQuery) => Promise<OneDayActionResult>
  // The Role filter (2026-11-25).
  loadRoles: () => Promise<Role[]>
}

// Item Change History / Item Conversion Group History (2026-11-25).
export interface ItemChangeHistorySource {
  fetch: (query: ItemHistoryQuery) => Promise<ItemChangeResult>
  loadItems: () => Promise<Item[]>
}

export interface ItemConversionGroupSource {
  fetch: (query: ItemHistoryQuery) => Promise<ItemConversionGroupResult>
  loadItems: () => Promise<Item[]>
}

export interface IdWiseSource {
  fetch: (stockId: number) => Promise<IdWiseReportResult>
}
