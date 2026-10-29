<script setup lang="ts">
import { onMounted, ref } from 'vue'
import PrintPreviewModal from '@/components/ui/PrintPreviewModal.vue'
import { cashTxnDetailsApi } from '@/lib/cashTxnDetailsApi'
import { ApiError } from '@/lib/api'
import type { CashTxnPrintReceipt } from '@/types'

/*
|--------------------------------------------------------------------------
| Thermal print preview — GET /cash-txn-details/print-report?id=
|--------------------------------------------------------------------------
| The one print endpoint the backend has: its ?id= branch returns a
| pre-formatted receipt for a single cash transaction. Nothing else is
| fetched for it. The overlay, buttons and print isolation come from
| PrintPreviewModal, which replaced this file's own unscoped
| `body * { visibility: hidden }` rule — that rule stayed loaded once Cash
| Management had been opened and blanked every later full-page print.
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
</script>

<template>
  <PrintPreviewModal
    title="Print Receipt"
    :subtitle="`Cash transaction #${txnId}`"
    :is-loading="isLoading"
    :error="loadError"
    :ready="receipt !== null"
    @close="emit('close')"
  >
    <div v-if="receipt" class="font-mono text-xs text-slate-900 print:max-w-[4in] print:text-black">
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
  </PrintPreviewModal>
</template>
