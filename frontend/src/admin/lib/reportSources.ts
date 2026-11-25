import { onMounted, ref } from 'vue'
import type {
  ConsolidatedSource,
  IdWiseSource,
  ItemChangeHistorySource,
  ItemConversionGroupSource,
  ItemsObcbSource,
  OneDayActionSource,
  ReportHead,
} from '@/lib/reportSources'
import { itemsApi, rolesApi, userDetailsApi } from './resources'
import { adminItemHistoryApi, adminOneDayActionApi, adminStockReportsApi } from './reportsApi'

// The report screens' data in the admin console (2026-11-24): every user in
// the party pickers, and the /admin/reports routes.

const lookups = {
  loadUsers: () => userDetailsApi.list(),
  loadItems: () => itemsApi.list(),
}

export const adminItemsObcbSource: ItemsObcbSource = {
  fetch: adminStockReportsApi.getItemsObcb,
  exportCsv: adminStockReportsApi.exportItemsObcb,
  ...lookups,
}

export const adminConsolidatedSource: ConsolidatedSource = {
  fetch: adminStockReportsApi.getConsolidated,
  exportCsv: adminStockReportsApi.exportConsolidated,
  ...lookups,
}

export const adminOneDayActionSource: OneDayActionSource = {
  fetch: adminOneDayActionApi.get,
  loadRoles: () => rolesApi.list(),
  ...lookups,
}

export const adminItemChangeHistorySource: ItemChangeHistorySource = {
  fetch: adminItemHistoryApi.getItemChanges,
  loadItems: lookups.loadItems,
}

export const adminItemConversionGroupSource: ItemConversionGroupSource = {
  fetch: adminItemHistoryApi.getConversionGroups,
  loadItems: lookups.loadItems,
}

export const adminIdWiseSource: IdWiseSource = {
  fetch: adminStockReportsApi.getIdWise,
}

/** The HEAD users the reports cover, sorted by name. */
export function useReportHeads() {
  const heads = ref<ReportHead[] | null>(null)
  const error = ref('')

  async function load() {
    error.value = ''
    try {
      const rows = await userDetailsApi.list('HEAD')
      heads.value = rows
        .map((u) => ({ id: u.id, name: u.name || u.user_name || `#${u.id}` }))
        .sort((a, b) => a.name.localeCompare(b.name))
    } catch {
      heads.value = []
      error.value = "Couldn't load the heads. Refresh the page to try again."
    }
  }

  onMounted(load)
  return { heads, error, reload: load }
}
