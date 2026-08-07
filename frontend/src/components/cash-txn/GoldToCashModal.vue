<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { X, Trash, Plus } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { useToastStore } from '@/stores/toast'
import type { BankDetail, UserDetail } from '@/types'

// UI-only for now — same situation as Purchase Gold: no backend endpoint
// exists (no controller, no route). Mirrors the legacy "Add Gold To Cash"
// dialog's layout so it's ready once a backend flow exists to save it
// against. Note the party order is reversed from the other quick-action
// modals — the legacy dialog reads "<counterparty> to <head> :", since cash
// flows from the head back to the counterparty in exchange for gold.
const props = defineProps<{
  headUser: UserDetail
  counterpartyUser: UserDetail
  banks: BankDetail[]
}>()

const emit = defineEmits<{ close: [] }>()

const toastStore = useToastStore()

type SourceType = 'CASH_ON_HAND' | 'BANK'
interface AmountSourceRow {
  sourceType: SourceType | null
  bankId: number | null
  amount: number | null
}

const bankOptions = computed(() => props.banks.map((b) => ({ value: b.bank_id, label: b.bank_name })))
const sourceTypeOptions: { value: SourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]

const totalGold = ref<number | null>(null)
const touch = ref<number | null>(100)
const purity = ref<number | null>(null)
const perGramPurityRate = ref<number | null>(null)
const totalCash = ref<number | null>(null)
const remarks = ref('')

const amountSources = reactive<AmountSourceRow[]>([{ sourceType: null, bankId: null, amount: null }])

function addSourceRow() {
  amountSources.push({ sourceType: null, bankId: null, amount: null })
}
function removeSourceRow(index: number) {
  if (amountSources.length > 1) amountSources.splice(index, 1)
}

function handleSave() {
  toastStore.show(
    'Gold To Cash isn’t connected to the backend yet — there’s no endpoint to save this to.',
    'error',
  )
}
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-8 print:hidden"
    >
      <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
          <div class="flex items-center gap-3">
            <h2 class="text-base font-semibold text-slate-900">Add Gold To Cash</h2>
            <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white">
              Head given cash for return GOLD
            </span>
          </div>
          <button
            type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="flex flex-col gap-5 px-6 py-5">
          <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            Not wired up yet — there's no backend endpoint for Gold To Cash. This is the form
            layout only; Save won't submit anything.
          </p>

          <p class="text-sm text-slate-600">
            <span class="font-semibold text-slate-800">{{ counterpartyUser.name }}</span>
            to
            <span class="font-semibold text-slate-800">{{ headUser.name }}</span> :
          </p>

          <div class="overflow-x-auto">
            <div class="grid gap-4 sm:grid-cols-2">
              <table class="w-full min-w-[320px] overflow-hidden rounded-lg border border-slate-200 text-sm">
                <thead>
                  <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                    <th class="px-3 py-2">{{ counterpartyUser.name }}</th>
                    <th class="px-3 py-2">Cash</th>
                    <th class="px-3 py-2">RTGS</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Grams</th>
                    <th class="px-3 py-2">Purity</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="bg-emerald-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                    <td class="px-3 py-2">{{ counterpartyUser.rak_cash_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ counterpartyUser.rak_rtgs_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{
                        (counterpartyUser.rak_cash_balance + counterpartyUser.rak_rtgs_balance).toLocaleString()
                      }}
                    </td>
                    <td class="px-3 py-2">{{ counterpartyUser.grams_grand_total.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ counterpartyUser.purity_grand_total.toLocaleString() }}</td>
                  </tr>
                  <tr class="bg-rose-50/60 text-slate-400">
                    <td class="px-3 py-2 font-medium">CB</td>
                    <td class="px-3 py-2" colspan="5">Not calculated — backend flow doesn't exist yet</td>
                  </tr>
                </tbody>
              </table>

              <table class="w-full min-w-[320px] overflow-hidden rounded-lg border border-slate-200 text-sm">
                <thead>
                  <tr class="bg-amber-50 text-left text-xs font-semibold text-slate-600">
                    <th class="px-3 py-2">{{ headUser.name }}</th>
                    <th class="px-3 py-2">Cash</th>
                    <th class="px-3 py-2">RTGS</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Grams</th>
                    <th class="px-3 py-2">Purity</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="bg-emerald-50/60">
                    <td class="px-3 py-2 font-medium text-slate-700">OB</td>
                    <td class="px-3 py-2">{{ headUser.rak_cash_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headUser.rak_rtgs_balance.toLocaleString() }}</td>
                    <td class="px-3 py-2">
                      {{ (headUser.rak_cash_balance + headUser.rak_rtgs_balance).toLocaleString() }}
                    </td>
                    <td class="px-3 py-2">{{ headUser.grams_grand_total.toLocaleString() }}</td>
                    <td class="px-3 py-2">{{ headUser.purity_grand_total.toLocaleString() }}</td>
                  </tr>
                  <tr class="bg-rose-50/60 text-slate-400">
                    <td class="px-3 py-2 font-medium">CB</td>
                    <td class="px-3 py-2" colspan="5">Not calculated — backend flow doesn't exist yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <BaseInput
            id="gtc_total_gold"
            :model-value="totalGold === null ? '' : String(totalGold)"
            label="Total Gold"
            type="number"
            step="0.001"
            size="sm"
            @update:model-value="(v) => (totalGold = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="gtc_touch"
            :model-value="touch === null ? '' : String(touch)"
            label="Touch"
            type="number"
            step="0.01"
            size="sm"
            @update:model-value="(v) => (touch = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="gtc_purity"
            :model-value="purity === null ? '' : String(purity)"
            label="Purity"
            type="number"
            step="0.0001"
            size="sm"
            @update:model-value="(v) => (purity = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="gtc_per_gram_purity_rate"
            :model-value="perGramPurityRate === null ? '' : String(perGramPurityRate)"
            label="Per Gram Purity Rate"
            type="number"
            step="0.01"
            size="sm"
            @update:model-value="(v) => (perGramPurityRate = v === '' ? null : Number(v))"
          />

          <BaseInput
            id="gtc_total_cash"
            :model-value="totalCash === null ? '' : String(totalCash)"
            label="Total Cash"
            type="number"
            step="0.01"
            size="sm"
            @update:model-value="(v) => (totalCash = v === '' ? null : Number(v))"
          />

          <BaseTextarea id="gtc_remarks" v-model="remarks" label="Remarks" size="sm" :rows="2" />

          <div>
            <div class="mb-2 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-slate-900">Amount Sources</h3>
              <BaseButton variant="secondary" type="button" :icon="Plus" @click="addSourceRow">
                Add source
              </BaseButton>
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
              <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="w-10 px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      S.No.
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Source
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Bank
                    </th>
                    <th class="px-3 py-2 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                      Amount
                    </th>
                    <th class="w-10 px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(row, index) in amountSources" :key="index">
                    <td class="px-3 py-2 text-slate-500">{{ index + 1 }}</td>
                    <td class="px-3 py-2">
                      <BaseSelect
                        :model-value="row.sourceType"
                        size="sm"
                        placeholder="Choose source…"
                        :options="sourceTypeOptions"
                        @update:model-value="(v) => (row.sourceType = v as SourceType | null)"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <BaseSelect
                        :model-value="row.bankId"
                        size="sm"
                        placeholder="Choose bank…"
                        :disabled="row.sourceType !== 'BANK'"
                        :options="bankOptions"
                        @update:model-value="(v) => (row.bankId = v as number | null)"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <BaseInput
                        :model-value="row.amount === null ? '' : String(row.amount)"
                        type="number"
                        step="0.01"
                        size="sm"
                        @update:model-value="(v) => (row.amount = v === '' ? null : Number(v))"
                      />
                    </td>
                    <td class="px-3 py-2 text-right">
                      <button
                        type="button"
                        class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                        aria-label="Remove source"
                        :disabled="amountSources.length === 1"
                        @click="removeSourceRow(index)"
                      >
                        <Trash class="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex items-center gap-3 border-t border-slate-200 pt-4">
            <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
            <BaseButton type="button" class="ml-auto" @click="handleSave">Save</BaseButton>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
