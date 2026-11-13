<script setup lang="ts">
import { onMounted, ref } from 'vue'
import CashToGoldModal from './CashToGoldModal.vue'
import GoldToCashModal from './GoldToCashModal.vue'
import PurchaseGoldModal from './PurchaseGoldModal.vue'
import SaleGoldModal from './SaleGoldModal.vue'
import { itemsApi } from '@/lib/itemsApi'
import { bankDetailsApi } from '@/lib/bankDetailsApi'
import { useToastStore } from '@/stores/toast'
import type { BankDetail, GoldEntryInput, GoldEntryKind, Item } from '@/types'

/*
|--------------------------------------------------------------------------
| Opens the right gold modal in "correct" mode (BACKEND_TODO §17 phase 3)
|--------------------------------------------------------------------------
| The four gold modals need the item and bank lists; this loads them, then
| renders the one matching the entry's kind with `correction` set, so the
| operator corrects the entry in the same form, with the same calculations,
| they used to create it.
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  goldEntryId: number
  kind: GoldEntryKind
  input: GoldEntryInput
  headName: string
  customerName: string
}>()

const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()
const items = ref<Item[]>([])
const banks = ref<BankDetail[]>([])
const isReady = ref(false)

async function reloadItems() {
  items.value = await itemsApi.list()
}
async function reloadBanks() {
  banks.value = await bankDetailsApi.list()
}

onMounted(async () => {
  try {
    await Promise.all([reloadItems(), reloadBanks()])
  } catch {
    toast.show('Could not load items and banks for the correction form.', 'error')
  } finally {
    isReady.value = true
  }
})

const correction = { goldEntryId: props.goldEntryId, input: props.input }
</script>

<template>
  <template v-if="isReady">
    <CashToGoldModal
      v-if="kind === 'cash_to_gold'"
      :head-id="input.head_id"
      :head-name="headName"
      :customer-id="input.customer_id"
      :customer-name="customerName"
      :items="items"
      :banks="banks"
      :reload-items="reloadItems"
      :reload-banks="reloadBanks"
      :correction="correction"
      @close="emit('close')"
      @saved="emit('saved')"
    />
    <GoldToCashModal
      v-else-if="kind === 'gold_to_cash'"
      :head-id="input.head_id"
      :head-name="headName"
      :customer-id="input.customer_id"
      :customer-name="customerName"
      :banks="banks"
      :reload-banks="reloadBanks"
      :correction="correction"
      @close="emit('close')"
      @saved="emit('saved')"
    />
    <PurchaseGoldModal
      v-else-if="kind === 'purchase'"
      :head-id="input.head_id"
      :head-name="headName"
      :customer-id="input.customer_id"
      :customer-name="customerName"
      :items="items"
      :banks="banks"
      :reload-items="reloadItems"
      :reload-banks="reloadBanks"
      :correction="correction"
      @close="emit('close')"
      @saved="emit('saved')"
    />
    <SaleGoldModal
      v-else-if="kind === 'sale'"
      :head-id="input.head_id"
      :head-name="headName"
      :customer-id="input.customer_id"
      :customer-name="customerName"
      :items="items"
      :banks="banks"
      :reload-items="reloadItems"
      :reload-banks="reloadBanks"
      :correction="correction"
      @close="emit('close')"
      @saved="emit('saved')"
    />
  </template>
</template>
