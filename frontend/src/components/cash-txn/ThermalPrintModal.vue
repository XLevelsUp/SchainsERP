<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { X } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import type { CashTxnPrintReceipt } from '@/types'

/*
|--------------------------------------------------------------------------
| Thermal print preview — GET /cash-txn-details/print-report?id=
|--------------------------------------------------------------------------
| Deliberately not BaseModal: BaseModal's overlay is print:hidden (correct
| for every other dialog — nobody wants a confirm modal's backdrop printing
| over their page), which would hide this receipt too since it's the one
| dialog that's supposed to print. The @media print block below isolates
| just the receipt instead.
|
| ob_amount/cb_amount can come back null — see CashTxnPrintReceipt's comment,
| that's a backend field-name bug (sender_ob/sender_cb don't exist on the
| model), not a rendering bug here.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ txnId: number }>()
const emit = defineEmits<{ close: [] }>()

const receipt = ref<CashTxnPrintReceipt | null>(null)
const isLoading = ref(true)
const loadError = ref('')

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    receipt.value = await cashTxnDetailsApi.getPrintReceipt(props.txnId)
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the print receipt.'
  } finally {
    isLoading.value = false
  }
}
onMounted(load)

function formatAmount(value: string | number | null): string {
  if (value === null || value === undefined) return '—'
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : String(value)
}

function handlePrint() {
  window.print()
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-8">
      <div class="thermal-receipt-print-root w-full max-w-sm rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 print:hidden">
          <h2 class="text-sm font-semibold text-slate-900">Print Receipt</h2>
          <button
            type="button"
            class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div v-if="isLoading" class="px-4 py-10 text-center text-sm text-slate-500 print:hidden">
          Loading…
        </div>
        <p v-else-if="loadError" class="px-4 py-4 text-sm text-red-700 print:hidden">{{ loadError }}</p>

        <div v-else-if="receipt" class="px-4 py-4 font-mono text-xs text-slate-900">
          <p class="text-center text-sm font-bold underline">{{ receipt.heading }}</p>
          <p class="mt-1 flex items-center justify-between gap-2">
            <span>{{ receipt.given_by_name }}</span>
            <span>To</span>
            <span>{{ receipt.given_to_name }}</span>
          </p>
          <p class="mt-1 text-center">{{ receipt.date }}</p>
          <p class="mt-2 flex justify-between gap-2">
            <span>{{ receipt.ob_label }}</span>
            <span class="tabular-nums">{{ formatAmount(receipt.ob_amount) }}</span>
          </p>
          <div class="mt-1 grid grid-cols-2 text-left">
            <div>
              <p class="font-semibold underline">Billno</p>
              <p class="tabular-nums">{{ receipt.bill_no }}</p>
            </div>
            <div>
              <p class="font-semibold underline">Amount</p>
              <p class="tabular-nums">{{ formatAmount(receipt.amount) }}</p>
            </div>
          </div>
          <p class="mt-1 flex justify-between gap-2">
            <span>{{ receipt.cb_label }}</span>
            <span class="tabular-nums">{{ formatAmount(receipt.cb_amount) }}</span>
          </p>
          <p v-if="receipt.remarks" class="mt-2 text-center">{{ receipt.remarks }}</p>
        </div>

        <div class="flex items-center gap-3 border-t border-slate-200 px-4 py-3 print:hidden">
          <BaseButton type="button" :disabled="!receipt" @click="handlePrint">Print</BaseButton>
          <BaseButton variant="secondary" type="button" @click="emit('close')">Close</BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .thermal-receipt-print-root,
  .thermal-receipt-print-root * {
    visibility: visible;
  }
  .thermal-receipt-print-root {
    position: fixed;
    inset: 0;
    max-width: 4in;
    margin: 0;
    box-shadow: none;
  }
}
</style>
