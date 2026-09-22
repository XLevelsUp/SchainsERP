import { api, type ApiResponse } from './api'
import { downloadFile } from './download'
import type {
  ConsolidatedQuery,
  ConsolidatedResult,
  IdWiseReportResult,
  ItemsObcbQuery,
  ItemsObcbResult,
} from '@/types'

// Both routes sit inside the `v1/stock` prefix group in routes/api.php.
const RESOURCE = '/stock/reports'

function buildQuery<T extends object>(params: T): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const stockReportsApi = {
  // Running OB/CB ledger. Scoped to the acting user as the head (the
  // controller resolves that from the bearer token), narrowed by the
  // optional prefixed `employee_id`.
  getItemsObcb: (query: ItemsObcbQuery) =>
    api
      .get<ApiResponse<ItemsObcbResult>>(`${RESOURCE}/items-obcb${buildQuery(query)}`)
      .then((r) => r.data),

  // Outward/inward summary plus both detail lists in one response.
  getConsolidated: (query: ConsolidatedQuery) =>
    api
      .get<ApiResponse<ConsolidatedResult>>(`${RESOURCE}/consolidated${buildQuery(query)}`)
      .then((r) => r.data),

  // CSV counterparts of the two reports above. Same query parameters, same
  // filtering — the controllers set `is_export` themselves, which makes
  // ReportService skip pagination and return every matching row, so page_no
  // and page_size are pointless here and callers should omit them.
  //
  // These do NOT go through `api`: the response is a streamed CSV, not a
  // JSON envelope. See lib/download.ts for why a plain link cannot work.
  exportItemsObcb: (query: Omit<ItemsObcbQuery, 'page_no' | 'page_size'>) =>
    downloadFile(`${RESOURCE}/items-obcb/export${buildQuery(query)}`, 'history_items_obcb.csv'),

  // One file containing both sides; the controller writes the OUT rows then
  // the IN rows into a single CSV.
  exportConsolidated: (query: Omit<ConsolidatedQuery, 'page_no_out' | 'page_no_in' | 'page_size'>) =>
    downloadFile(`${RESOURCE}/consolidated/export${buildQuery(query)}`, 'consolidated_report.csv'),

  // Lot lineage for one stock row: the parent lot it belongs to, every
  // child transaction drawn from that lot, and a received/consumed/balance
  // reconciliation. 404s if stock_id doesn't exist.
  getIdWise: (stockId: number) =>
    api
      .get<ApiResponse<IdWiseReportResult>>(`${RESOURCE}/id-wise${buildQuery({ stock_id: stockId })}`)
      .then((r) => r.data),
}
