<script setup lang="ts">
import { computed, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { addDecimal, isValidOpeningValue, unitOf } from '@/lib/openingBalance'
import type { OpeningBalanceField } from '@/types'

/*
| The legacy "Create new Opening Balance Update" modal (2026-11-24):
| Remarks, Previous balance (read-only), Value update, Closing balance
| (previous + value, read-only). A negative value reduces the balance.
|
| On an existing user it saves straight away, so it asks for the special
| password here. On a new user it only fills the form; the password is
| asked once, with the user's save.
*/

const props = withDefaults(
  defineProps<{
    title: string
    field: OpeningBalanceField
    previous: string
    requiresPassword: boolean
    submitting?: boolean
    error?: string
  }>(),
  { submitting: false, error: '' },
)

const emit = defineEmits<{
  save: [entry: { value: string; remarks: string; specialPassword: string }]
  close: []
}>()

const remarks = ref('')
const value = ref('')
const specialPassword = ref('')
const valueError = ref('')
const passwordError = ref('')
const remarksError = ref('')

const unit = computed(() => unitOf(props.field))
const closing = computed(() => addDecimal(props.previous, isValidOpeningValue(value.value) ? value.value.trim() : '0'))

function figure(amount: string): string {
  return Number(amount).toLocaleString('en-IN', {
    minimumFractionDigits: unit.value === 'g' ? 3 : 2,
    maximumFractionDigits: 4,
  })
}

// Digits, one leading minus and one decimal point.
function sanitizeValue(raw: string): string {
  const cleaned = raw.replace(/[^\d.-]/g, '')
  const negative = cleaned.startsWith('-')
  const [whole = '', ...rest] = cleaned.replace(/-/g, '').split('.')
  const decimals = rest.length ? `.${rest.join('').slice(0, 4)}` : ''
  return `${negative ? '-' : ''}${whole}${decimals}`
}

function handleSave() {
  if (props.submitting) return
  valueError.value = isValidOpeningValue(value.value)
    ? ''
    : 'Enter a value other than 0, with up to 4 decimals (negative to reduce).'
  passwordError.value = props.requiresPassword && !specialPassword.value ? 'Enter your special password.' : ''
  remarksError.value = remarks.value.trim().length > 500 ? 'Remarks must be 500 characters or fewer.' : ''
  if (valueError.value || passwordError.value || remarksError.value) return
  emit('save', { value: value.value.trim(), remarks: remarks.value, specialPassword: specialPassword.value })
}
</script>

<template>
  <BaseModal :title="title" max-width="max-w-lg" @close="emit('close')">
    <form class="flex flex-col gap-4" novalidate @submit.prevent="handleSave">
      <BaseTextarea id="ob_remarks" v-model="remarks" label="Remarks" :rows="3" :error="remarksError" />

      <div>
        <span class="mb-1 block text-sm font-medium text-slate-700">Previous balance ({{ unit }})</span>
        <div
          id="ob_previous"
          class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-right text-sm text-slate-700 tabular-nums"
        >
          {{ figure(previous) }}
        </div>
      </div>

      <BaseInput
        id="ob_value"
        v-model="value"
        :label="`Value update (${unit})`"
        required
        inputmode="decimal"
        placeholder="e.g. 10.5 or -2"
        :sanitize="sanitizeValue"
        :error="valueError"
      />

      <div>
        <span class="mb-1 block text-sm font-medium text-slate-700">Closing balance ({{ unit }})</span>
        <div
          id="ob_closing"
          class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-right text-sm font-semibold text-slate-900 tabular-nums"
        >
          {{ figure(closing) }}
        </div>
      </div>

      <div v-if="requiresPassword">
        <BaseInput
          id="ob_special_password"
          v-model="specialPassword"
          label="Special password"
          type="password"
          autocomplete="off"
          required
          :error="passwordError"
        />
        <p class="mt-1 text-xs text-slate-500">This changes the balance straight away and is recorded.</p>
      </div>
      <p v-else class="text-xs text-slate-500">
        Saved with the new user; you'll enter your special password once, with the save.
      </p>

      <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

      <div class="flex items-center justify-between border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="submitting" @click="emit('close')">Close</BaseButton>
        <BaseButton type="submit" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
