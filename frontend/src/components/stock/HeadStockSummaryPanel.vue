<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { Printer } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { headStocksApi } from '@/lib/headStocksApi'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { HeadStockSummary } from '@/types'

/*
|--------------------------------------------------------------------------
| Head Stocks panel — GET /stock-details/head-stocks
|--------------------------------------------------------------------------
| Legacy "Stock Details" screen's top-left item-wise balance summary for
| the logged-in head: grams/%/purity per item, plus grand total, cash
| balance, and active-orders weight. The date/time fields replay the
| balance as of that moment (head_txn_from_date/head_txn_from_time);
| left blank, the backend returns live totals.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()

const summary = ref<HeadStockSummary | null>(null)
const isLoading = ref(false)
const loadError = ref('')
const asOfDate = ref('')
const asOfTime = ref('')

function formatNumber(value: number) {
  return Number.isFinite(value)
    ? value.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
    : '0.000'
}

async function load() {
  if (!auth.user) return
  isLoading.value = true
  loadError.value = ''
  try {
    summary.value = await headStocksApi.get(auth.user.user_id, {
      head_txn_from_date: asOfDate.value || undefined,
      head_txn_from_time: asOfTime.value || undefined,
    })
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load head stocks.'
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
watch([asOfDate, asOfTime], load)

function printPanel() {
  window.print()
}

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-900">Head Stocks</h2>
      <button
        type="button"
        class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
        aria-label="Print head stocks"
        @click="printPanel"
      >
        <Printer class="h-4 w-4" />
      </button>
    </div>

    <div class="flex gap-2 border-b border-slate-200 px-4 py-3">
      <BaseInput v-model="asOfDate" type="date" size="sm" class="flex-1" />
      <BaseInput v-model="asOfTime" type="time" size="sm" class="flex-1" />
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>

    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">Loading…</div>

    <div v-else-if="summary" class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <th scope="col" class="px-4 py-2 text-left">Item</th>
            <th scope="col" class="px-3 py-2 text-right">Grams</th>
            <th scope="col" class="px-3 py-2 text-right">%</th>
            <th scope="col" class="px-4 py-2 text-right">Purity</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="summary.items.length === 0">
            <td colspan="4" class="px-4 py-6 text-center text-slate-500">No items mapped yet.</td>
          </tr>
          <tr v-for="item in summary.items" :key="item.item_id">
            <td class="px-4 py-2 text-slate-700">{{ item.item_name }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">
              {{ formatNumber(item.grams) }}
            </td>
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">
              {{ formatNumber(item.percentage) }}
            </td>
            <td class="px-4 py-2 text-right tabular-nums text-slate-700">
              {{ formatNumber(item.purity) }}
            </td>
          </tr>
        </tbody>
        <tfoot class="divide-y divide-slate-100 border-t border-slate-200">
          <tr class="font-semibold text-slate-900">
            <td class="px-4 py-2">Total</td>
            <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(summary.totals.grams) }}</td>
            <td class="px-3 py-2"></td>
            <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(summary.totals.purity) }}</td>
          </tr>
          <tr class="text-slate-700">
            <td class="px-4 py-2" colspan="3">Cash</td>
            <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(summary.cash_balance) }}</td>
          </tr>
          <tr class="text-slate-700">
            <td class="px-4 py-2" colspan="3">Active Orders</td>
            <td class="px-4 py-2 text-right tabular-nums">{{ formatNumber(summary.active_orders) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </BaseCard>
</template>
