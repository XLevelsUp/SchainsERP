<script setup lang="ts">
import ConsolidatedReportView from '@/views/ConsolidatedReportView.vue'
import IdWiseReportView from '@/views/IdWiseReportView.vue'
import ItemsObcbReportView from '@/views/ItemsObcbReportView.vue'
import OneDayActionView from '@/views/OneDayActionView.vue'
import ItemChangeHistoryView from '@/views/ItemChangeHistoryView.vue'
import ItemConversionGroupHistoryView from '@/views/ItemConversionGroupHistoryView.vue'
import {
  adminConsolidatedSource,
  adminIdWiseSource,
  adminItemChangeHistorySource,
  adminItemConversionGroupSource,
  adminItemsObcbSource,
  adminOneDayActionSource,
  useReportHeads,
} from '../../lib/reportSources'
import type { AdminReport } from '../../routes'

/*
| The stock reports in the admin console (2026-11-24): the heads' own
| report screens, given the console's data source and every head.
|  - Items OB & CB / Consolidated: one section per head.
|  - One Day Action: every head's entries in one feed, with a Head filter.
|  - ID Wise: any stock ID.
|  - Item Conversion Group History / Item Change History (2026-11-25):
|    every user's, with a Head filter.
*/

const props = defineProps<{ report: AdminReport }>()

const { heads, error } = useReportHeads()
</script>

<template>
  <IdWiseReportView v-if="props.report === 'id-wise'" :source="adminIdWiseSource" />

  <div v-else-if="heads === null" class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">
    Loading heads…
  </div>

  <template v-else>
    <p v-if="error" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ error }}</p>
    <ItemsObcbReportView v-if="props.report === 'items-obcb'" :source="adminItemsObcbSource" :heads="heads" />
    <ConsolidatedReportView v-else-if="props.report === 'consolidated'" :source="adminConsolidatedSource" :heads="heads" />
    <ItemConversionGroupHistoryView
      v-else-if="props.report === 'item-conversion-groups'"
      :source="adminItemConversionGroupSource"
      :heads="heads"
    />
    <ItemChangeHistoryView v-else-if="props.report === 'item-changes'" :source="adminItemChangeHistorySource" :heads="heads" />
    <OneDayActionView v-else :source="adminOneDayActionSource" :heads="heads" />
  </template>
</template>
