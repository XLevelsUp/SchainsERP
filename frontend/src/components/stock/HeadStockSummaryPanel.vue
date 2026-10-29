<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Printer } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import HeadStockPrintModal from '@/components/stock/HeadStockPrintModal.vue'
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
|
| The printer button opens HeadStockPrintModal: a preview of the legacy
| slip (head name, timestamp, Item Name/Grams/Purity), fetched from this
| same endpoint with this panel's as-of filters when it opens, then printed
| on its own rather than with the whole page.
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

// The signed-in head's name titles the slip.
//
// Known gap: the legacy slip reads "NIHAA(PR)" — name plus the user's
// `type`, the same convention UserPickerPanel's formatUserLabel uses. The
// login response (AuthUser) carries only user_id/name/user_name/role_id,
// so `type` is not available here and the suffix cannot be reproduced
// without either adding it to the auth payload (backend) or fetching
// GET /user-details/{id} just for this label. Printing the bare name
// rather than inventing a suffix.
const printTitle = computed(() => auth.user?.name || auth.user?.user_name || '')

const isPrintOpen = ref(false)

defineExpose({ refresh: load })
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 py-2.5 pr-3 pl-3">
      <h2 class="text-sm font-semibold text-slate-900">Head Stocks</h2>
      <button
        type="button"
        class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-ink-900 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Print head stocks"
        title="Print head stocks slip"
        :disabled="!summary"
        @click="isPrintOpen = true"
      >
        <Printer class="h-4 w-4" />
      </button>
    </div>

    <!--
      Labelled "As of" because that is what these do — replay the balance at
      that moment — and two bare date/time boxes didn't say so. Blank = live.
    -->
    <!--
      Each input sits in its own min-w-0 box: BaseInput forwards `class` to
      the <input>, not its wrapper, so flex sizing has to live out here or
      the native time field pushes past the panel edge.
    -->
    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50/60 px-3 py-2">
      <span class="shrink-0 text-xs font-medium text-slate-500">As of</span>
      <div class="min-w-0 flex-[3]">
        <BaseInput v-model="asOfDate" type="date" size="sm" aria-label="As of date" clearable />
      </div>
      <div class="min-w-0 flex-[2]">
        <BaseInput v-model="asOfTime" type="time" size="sm" aria-label="As of time" clearable />
      </div>
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>

    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">
      Loading head stocks…
    </div>

    <div v-else-if="summary" class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <!--
            Tight horizontal padding on purpose: this panel lives in a 340px
            column, and at the old px-3/px-4 the Purity column was pushed out
            of view behind a horizontal scroll.
          -->
          <tr
            class="border-b border-slate-100 font-figure text-[11px] font-semibold tracking-[0.06em] whitespace-nowrap text-slate-500 uppercase"
          >
            <th scope="col" class="py-2 pr-2 pl-3 text-left">Item</th>
            <th scope="col" class="px-2 py-2 text-right">Grams</th>
            <th scope="col" class="px-2 py-2 text-right">%</th>
            <th scope="col" class="py-2 pr-3 pl-2 text-right">Purity g</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="summary.items.length === 0">
            <td colspan="4" class="px-3 py-6 text-center text-slate-500">No items mapped yet.</td>
          </tr>
          <tr v-for="item in summary.items" :key="item.item_id" class="hover:bg-slate-50/70">
            <td class="py-1.5 pr-2 pl-3 text-slate-700">{{ item.item_name }}</td>
            <td class="px-2 py-1.5 text-right font-figure tabular-nums text-slate-900">
              {{ formatNumber(item.grams) }}
            </td>
            <td class="px-2 py-1.5 text-right font-figure tabular-nums text-slate-500">
              {{ formatNumber(item.percentage) }}
            </td>
            <td class="py-1.5 pr-3 pl-2 text-right font-figure tabular-nums text-gold-700">
              {{ formatNumber(item.purity) }}
            </td>
          </tr>
        </tbody>
        <tfoot class="border-t border-slate-300">
          <tr class="font-semibold text-ink-900">
            <td class="py-2 pr-2 pl-3">Total</td>
            <td class="px-2 py-2 text-right font-figure text-[15px] tabular-nums">
              {{ formatNumber(summary.totals.grams) }}
            </td>
            <td class="px-2 py-2"></td>
            <td class="py-2 pr-3 pl-2 text-right font-figure text-[15px] tabular-nums text-gold-700">
              {{ formatNumber(summary.totals.purity) }}
            </td>
          </tr>
          <tr class="border-t border-slate-100 text-slate-600">
            <td class="py-1.5 pr-2 pl-3" colspan="3">Cash</td>
            <td class="py-1.5 pr-3 pl-2 text-right font-figure tabular-nums text-slate-900">
              {{ formatNumber(summary.cash_balance) }}
            </td>
          </tr>
          <tr class="border-t border-slate-100 text-slate-600">
            <td class="py-1.5 pr-2 pl-3" colspan="3">Active Orders</td>
            <td class="py-1.5 pr-3 pl-2 text-right font-figure tabular-nums text-slate-900">
              {{ formatNumber(summary.active_orders) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </BaseCard>

  <HeadStockPrintModal
    v-if="isPrintOpen && auth.user"
    :user-id="auth.user.user_id"
    :title="printTitle"
    :as-of-date="asOfDate"
    :as-of-time="asOfTime"
    @close="isPrintOpen = false"
  />
</template>
