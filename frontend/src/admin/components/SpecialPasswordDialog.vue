<script setup lang="ts">
import { ref, watch } from 'vue'
import { ShieldAlert } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

/*
| Confirms a one-click sensitive change (e.g. deleting a user) with the
| admin's special password. Changes made through a form (assigning heads)
| ask for it in the form instead, so there's never a modal on a modal.
|
| The parent runs the change on `confirm` and passes back `error` (e.g. the
| 422 on special_password) to keep the dialog open, or closes it.
*/
const props = withDefaults(
  defineProps<{
    title: string
    message: string
    confirmLabel?: string
    destructive?: boolean
    submitting?: boolean
    error?: string
  }>(),
  { confirmLabel: 'Confirm', destructive: false, submitting: false, error: '' },
)

const emit = defineEmits<{ confirm: [specialPassword: string]; cancel: [] }>()

const password = ref('')
const localError = ref('')

// A new error from the parent means the password was rejected: clear the
// field for the next try.
watch(
  () => props.error,
  (next) => {
    if (next) password.value = ''
  },
)

function submit() {
  if (props.submitting) return
  localError.value = ''
  if (!password.value) {
    localError.value = 'Enter your special password.'
    return
  }
  emit('confirm', password.value)
}

function cancel() {
  if (props.submitting) return
  emit('cancel')
}
</script>

<template>
  <BaseModal :title="title" max-width="max-w-md" @close="cancel">
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex items-start gap-3">
        <ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
        <p class="text-sm text-slate-700">{{ message }}</p>
      </div>
      <BaseInput
        id="special_password_confirm"
        v-model="password"
        label="Special password"
        type="password"
        autocomplete="off"
        required
        :error="localError || error"
      />
      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="submitting" @click="cancel">
          Cancel
        </BaseButton>
        <BaseButton :variant="destructive ? 'outflow' : 'primary'" type="submit" :disabled="submitting">
          {{ submitting ? 'Working…' : confirmLabel }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
