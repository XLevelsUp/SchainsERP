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
| Metal Entry — GET /stock-details/available-metals (API doc #22)
|--------------------------------------------------------------------------
| Opens whenever a row's Item is set to the item literally named "Metal"
| (case-insensitive — the backend 400s on anything else) in StockOutPanel/
| StockInPanel/GmsOutPanel/GmsInPanel. userId is that panel's *source*
| user for the transaction direction (given_by), not the page-level head
| unconditionally — matches AvailableMetalResource's query
| (`where('given_to', $userId)`, i.e. lots previously given TO userId that
| they're now handing further along) and its comment ("party who gave it
| to us is givenBy"): the caller is drawing down whatever was given to
| them, so userId must be that same person.
|
| Required/Taken/Remaining reproduces the legacy header exactly: Required
| seeds from the row's Grams (if any) but stays editable, Taken sums the
| Taken column live, Remaining = Required - Taken. Only rows with Taken >
| 0 are returned on Save.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ itemId: number; userId: number; required?: number }>()
const emit = defineEmits<{ close: []; confirm: [rows: MetalPickerSelection[]] }>()

const rows = ref<AvailableMetalRow[]>([])
const taken = reactive<Record<number, number | null>>({})
const requiredGrams = ref(props.required ?? 0)
const isLoading = ref(true)
const loadError = ref('')

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    const data = await availableMetalsApi.list({ item_id: props.itemId, user_id: props.userId })
    rows.value = data
    for (const row of data) {
      taken[row.id] = null
    }
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load available metal stock.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

const totals = computed(() => {
  const grams = rows.value.reduce((sum, row) => sum + row.grams, 0)
  const purity = rows.value.reduce((sum, row) => sum + row.purity, 0)
  const takenSum = rows.value.reduce((sum, row) => sum + (taken[row.id] ?? 0), 0)
  return { grams, purity, taken: takenSum, remaining: requiredGrams.value - takenSum }
})

function handleSave() {
  const selection: MetalPickerSelection[] = rows.value
    .filter((row) => (taken[row.id] ?? 0) > 0)
    .map((row) => ({ ...row, taken: taken[row.id] ?? 0 }))
  emit('confirm', selection)
}

function fmt(n: number): string {
  return n.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}
</script>

<template>
  <BaseModal title="Metal Entry" max-width="max-w-3xl" @close="emit('close')">
    <div class="grid grid-cols-3 gap-4">
      <BaseInput
        :model-value="String(requiredGrams)"
        label="Required"
        type="number"
        step="0.001"
        size="sm"
        @update:model-value="(v) => (requiredGrams = v === '' ? 0 : Number(v))"
      />
      <BaseInput :model-value="fmt(totals.taken)" label="Taken" size="sm" readonly />
      <BaseInput :model-value="fmt(totals.remaining)" label="Remaining" size="sm" readonly />
    </div>

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
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Grams</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Touch</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Purity</th>
            <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Party Name</th>
            <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Taken</th>
            <th class="px-3 py-2 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Balance</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="row in rows" :key="row.id">
            <td class="px-3 py-2 text-sm tabular-nums text-slate-700">{{ row.id }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.grams) }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.touch) }}</td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.purity) }}</td>
            <td class="px-3 py-2 text-sm text-slate-700">{{ row.party_name }}</td>
            <td class="px-3 py-2">
              <BaseInput
                :id="`taken_${row.id}`"
                :model-value="taken[row.id] === null ? '' : String(taken[row.id])"
                type="number"
                step="0.001"
                size="sm"
                @update:model-value="(v) => (taken[row.id] = v === '' ? null : Number(v))"
              />
            </td>
            <td class="px-3 py-2 text-right text-sm tabular-nums text-slate-700">{{ fmt(row.balance_grams) }}</td>
          </tr>
        </tbody>
        <tfoot class="bg-slate-50">
          <tr>
            <td class="px-3 py-2 text-xs font-semibold text-slate-500 uppercase">Total</td>
            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-900">{{ fmt(totals.grams) }}</td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums text-slate-900">{{ fmt(totals.purity) }}</td>
            <td class="px-3 py-2" colspan="3"></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
      <BaseButton type="button" :disabled="isLoading || rows.length === 0" @click="handleSave">Save</BaseButton>
      <BaseButton variant="secondary" type="button" @click="emit('close')">Cancel</BaseButton>
    </div>
  </BaseModal>
</template>
