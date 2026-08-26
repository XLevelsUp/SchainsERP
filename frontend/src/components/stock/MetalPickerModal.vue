<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { availableMetalsApi } from '@/lib/availableMetalsApi'
import { ApiError } from '@/lib/api'
import type { AvailableMetalRow, MetalPickerSelection } from '@/types'

/*
|--------------------------------------------------------------------------
| Metal Picker — GET /stock-details/available-metals (API doc #22)
|--------------------------------------------------------------------------
| TEST FEATURE — not wired into Stock Out / GMS / Item Change yet. Per the
| API doc's "IMPORTANT" note: this endpoint only returns the raw available
| stock rows (id, grams, touch, purity, party_name, balance_grams) — every
| Taken/Wastage input and the row/footer totals below are computed entirely
| client-side, nothing round-trips to the server until a real caller wires
| this modal's `confirm` payload into an actual submission endpoint
| (stock/out, stock/gms-out, stock/item-change).
|--------------------------------------------------------------------------
*/

const props = defineProps<{ itemId: number; userId: number }>()
const emit = defineEmits<{ close: []; confirm: [rows: MetalPickerSelection[]] }>()

const rows = ref<AvailableMetalRow[]>([])
const entries = reactive<Record<number, { taken: number | null; wastage: number | null }>>({})
const isLoading = ref(true)
const loadError = ref('')

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    const data = await availableMetalsApi.list({ item_id: props.itemId, user_id: props.userId })
    rows.value = data
    for (const row of data) {
      entries[row.id] = { taken: null, wastage: null }
    }
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load available metal stock.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

function rowTotal(row: AvailableMetalRow): number {
  const taken = entries[row.id]?.taken ?? 0
  return row.grams - taken
}

const footer = computed(() => {
  return rows.value.reduce(
    (acc, row) => {
      acc.grams += row.grams
      acc.balance += row.balance_grams
      acc.taken += entries[row.id]?.taken ?? 0
      acc.wastage += entries[row.id]?.wastage ?? 0
      acc.total += rowTotal(row)
      return acc
    },
    { grams: 0, balance: 0, taken: 0, wastage: 0, total: 0 },
  )
})

function handleConfirm() {
  const selection: MetalPickerSelection[] = rows.value
    .filter((row) => (entries[row.id]?.taken ?? 0) > 0)
    .map((row) => ({
      ...row,
      taken: entries[row.id]?.taken ?? 0,
      wastage: entries[row.id]?.wastage ?? 0,
      rowTotal: rowTotal(row),
    }))
  emit('confirm', selection)
}

function fmt(n: number): string {
  return n.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}
</script>

<template>
  <BaseModal title="Metal Picker" badge="Test feature" badge-class="bg-amber-500" max-width="max-w-6xl" @close="emit('close')">
    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      Prototype only — nothing here submits anywhere yet. Confirming just hands the computed rows
      back to the test page so you can inspect the payload shape.
    </p>

    <div v-if="isLoading" class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
      Loading available metal stock…
    </div>
    <p v-else-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ loadError }}
    </p>
    <p v-else-if="rows.length === 0" class="rounded-lg border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
      No available metal stock for this user.
    </p>

    <div v-else class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">ID</th>
            <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Party</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Grams</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Touch</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Purity</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Balance</th>
            <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Taken</th>
            <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Wastage</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Row total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="row in rows" :key="row.id">
            <td class="px-3 py-2 text-sm tabular-nums text-slate-700">{{ row.id }}</td>
            <td class="px-3 py-2 text-sm text-slate-700">{{ row.party_name }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.grams) }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.touch) }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.purity) }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.balance_grams) }}</td>
            <td class="px-3 py-2">
              <BaseInput
                :id="`taken_${row.id}`"
                :model-value="entries[row.id]?.taken === null ? '' : String(entries[row.id]?.taken)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (entries[row.id].taken = v === '' ? null : Number(v))"
              />
            </td>
            <td class="px-3 py-2">
              <BaseInput
                :id="`wastage_${row.id}`"
                :model-value="entries[row.id]?.wastage === null ? '' : String(entries[row.id]?.wastage)"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (entries[row.id].wastage = v === '' ? null : Number(v))"
              />
            </td>
            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-900">
              {{ fmt(rowTotal(row)) }}
            </td>
          </tr>
        </tbody>
        <tfoot class="bg-slate-50">
          <tr>
            <td class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase" colspan="2">Totals</td>
            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-900">{{ fmt(footer.grams) }}</td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-900">{{ fmt(footer.balance) }}</td>
            <td class="px-3 py-2 text-sm font-semibold tabular-nums text-slate-900">{{ fmt(footer.taken) }}</td>
            <td class="px-3 py-2 text-sm font-semibold tabular-nums text-slate-900">{{ fmt(footer.wastage) }}</td>
            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-900">{{ fmt(footer.total) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
      <BaseButton type="button" :disabled="isLoading || rows.length === 0" @click="handleConfirm">
        Confirm selection
      </BaseButton>
      <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
    </div>
  </BaseModal>
</template>
