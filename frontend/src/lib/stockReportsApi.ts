import { api as appApi, API_BASE_URL, type ApiClient, type ApiResponse } from './api'
import { appSession } from './authSession'
import { downloadFile, type DownloadRealm } from './download'
import type {
  ConsolidatedQuery,
  ConsolidatedResult,
  IdWiseReportResult,
  ItemsObcbQuery,
  ItemsObcbResult,
} from '@/types'

function buildQuery<T extends object>(params: T): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

/**
 * The stock reports. Written against ApiClient so the admin console reuses
 * it (admin/lib/reportsApi.ts) on its own routes (/admin/reports, where the
 * head is passed as head_id); `stockReportsApi` below is the app's
 * (/stock/reports, the head is the signed-in user).
 */
export function createStockReportsApi(api: ApiClient, resource: string, realm: DownloadRealm) {
  return {
    // Running OB/CB ledger, from the head's side, narrowed by the optional
    // prefixed `employee_id`.
    getItemsObcb: (query: ItemsObcbQuery) =>
      api.get<ApiResponse<ItemsObcbResult>>(`${resource}/items-obcb${buildQuery(query)}`).then((r) => r.data),

    // Outward/inward summary plus both detail lists in one response.
    getConsolidated: (query: ConsolidatedQuery) =>
      api.get<ApiResponse<ConsolidatedResult>>(`${resource}/consolidated${buildQuery(query)}`).then((r) => r.data),

    // CSV counterparts of the two reports above. Same query parameters, same
    // filtering — the controllers set `is_export` themselves, which makes
    // ReportService skip pagination and return every matching row, so page_no
    // and page_size are pointless here and callers should omit them.
    //
    // These do NOT go through `api`: the response is a streamed CSV, not a
    // JSON envelope. See lib/download.ts for why a plain link cannot work.
    exportItemsObcb: (query: Omit<ItemsObcbQuery, 'page_no' | 'page_size'>) =>
      downloadFile(`${resource}/items-obcb/export${buildQuery(query)}`, 'history_items_obcb.csv', realm),

    // One file containing both sides; the controller writes the OUT rows then
    // the IN rows into a single CSV.
    exportConsolidated: (query: Omit<ConsolidatedQuery, 'page_no_out' | 'page_no_in' | 'page_size'>) =>
      downloadFile(`${resource}/consolidated/export${buildQuery(query)}`, 'consolidated_report.csv', realm),

    // Lot lineage for one stock row: the parent lot it belongs to, every
    // child transaction drawn from that lot, and a received/consumed/balance
    // reconciliation. 404s if stock_id doesn't exist.
    getIdWise: (stockId: number) =>
      api
        .get<ApiResponse<IdWiseReportResult>>(`${resource}/id-wise${buildQuery({ stock_id: stockId })}`)
        .then((r) => r.data),
  }
}

export type StockReportsApi = ReturnType<typeof createStockReportsApi>

// Both routes sit inside the `v1/stock` prefix group in routes/api.php.
export const stockReportsApi = createStockReportsApi(appApi, '/stock/reports', {
  baseUrl: API_BASE_URL,
  session: appSession,
})
