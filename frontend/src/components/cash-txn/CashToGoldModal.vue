<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { X, Trash, Plus } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { useToastStore } from '@/stores/toast'
import type { BankDetail, Item, UserDetail } from '@/types'

// UI-only for now — same situation as Purchase Gold / Gold To Cash: no
// backend endpoint exists (no controller, no route; only SaleGoldController
// exists, hardcoded to type=SALE_GOLD). Party order matches the legacy
// dialog's "<counterparty> to <head> :" — the customer hands over cash and
// receives gold, so cash flows from the counterparty back to the head.
const props = defineProps<{
  headUser: UserDetail
  counterpartyUser: UserDetail
  items: Item[]
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

const itemOptions = computed(() => props.items.map((i) => ({ value: i.item_id, label: i.item_name })))
const bankOptions = computed(() => props.banks.map((b) => ({ value: b.bank_id, label: b.bank_name })))
const sourceTypeOptions: { value: SourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]

const totalCash = ref<number | null>(null)
const amountTransferToHead = ref(true)
const perGramCash = ref<number | null>(null)
const itemId = ref<number | null>(null)
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
    'Cash To Gold isn’t connected to the backend yet — there’s no endpoint to save this to.',
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
            <h2 class="text-base font-semibold text-slate-900">Add Cash To Gold</h2>
            <span class="rounded-full bg-red-600 px-2.5 py-1 text-xs font-semibold text-white">
              Customer given cash for giving GOLD
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
            Not wired up yet — there's no backend endpoint for Cash To Gold. This is the form
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
            id="ctg_total_cash"
            :model-value="totalCash === null ? '' : String(totalCash)"
            label="Total Cash"
            type="number"
            step="0.01"
            size="sm"
            @update:model-value="(v) => (totalCash = v === '' ? null : Number(v))"
          />

          <BaseCheckbox v-model="amountTransferToHead" label="Amount transfer to head" />

          <BaseInput
            id="ctg_per_gram_cash"
            :model-value="perGramCash === null ? '' : String(perGramCash)"
            label="Per Gram Cash"
            type="number"
            step="0.01"
            size="sm"
            class="sm:max-w-xs"
            @update:model-value="(v) => (perGramCash = v === '' ? null : Number(v))"
          />

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Purity</label>
            <input
              disabled
              placeholder="Derived once an item is selected — not available yet"
              class="w-full max-w-xs cursor-not-allowed rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-400"
            />
          </div>

          <BaseSelect
            id="ctg_item"
            :model-value="itemId"
            label="Item"
            size="sm"
            placeholder="Choose item…"
            :options="itemOptions"
            class="sm:max-w-sm"
            @update:model-value="(v) => (itemId = v as number | null)"
          />

          <BaseTextarea id="ctg_remarks" v-model="remarks" label="Remarks" size="sm" :rows="2" />

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
