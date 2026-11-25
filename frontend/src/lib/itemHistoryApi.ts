import { api } from './api'
import { createItemHistoryApi } from './itemHistory'

// The app's Item Change / Item Conversion history (2026-11-25): GET
// /stock/reports/item-changes and /stock/reports/item-conversion-groups,
// scoped server-side to the signed-in user (plus a head's mapped employees).
export const itemHistoryApi = createItemHistoryApi(api, '/stock/reports')
