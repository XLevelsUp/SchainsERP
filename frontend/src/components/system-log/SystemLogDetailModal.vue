<script setup lang="ts">
import { computed } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import { formatDateTime } from '@/lib/date'
import {
  SYSTEM_LOG_PARTY_KEYS,
  systemLogActionLabel,
  systemLogDescription,
  systemLogMetadataLabel,
  systemLogModuleLabel,
  systemLogPartyLabel,
  type SystemLogNameLookup,
} from '@/lib/systemLogLabels'
import type { SystemLog } from '@/types'

/*
| Read-only view of one system_logs row. Everything shown is already on the
| list row (SystemLogResource returns payload and metadata in the index), so
| this opens instantly without a GET /system-logs/{id} round trip.
*/

const props = defineProps<{
  log: SystemLog
  nameOf: SystemLogNameLookup
}>()

const emit = defineEmits<{ close: [] }>()

function formatValue(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (SYSTEM_LOG_PARTY_KEYS.has(key) && (typeof value === 'number' || typeof value === 'string')) {
    const id = Number(value)
    if (Number.isFinite(id)) return systemLogPartyLabel(id, props.nameOf)
  }
  if (Array.isArray(value) && value.every((v) => typeof v !== 'object')) return value.join(', ')
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

// An empty PHP array serialises as `[]`, so guard for arrays as well as null.
const metadataEntries = computed(() => {
  const metadata = props.log.metadata
  if (!metadata || Array.isArray(metadata)) return []
  return Object.entries(metadata).map(([key, value]) => ({
    key,
    label: systemLogMetadataLabel(key),
    value: formatValue(key, value),
  }))
})

const payloadJson = computed(() => {
  const payload = props.log.payload
  if (!payload || (Array.isArray(payload) && payload.length === 0)) return ''
  return JSON.stringify(payload, null, 2)
})

const performedBy = computed(() => {
  const user = props.log.user
  if (user) return user.user_name ? `${user.name} (${user.user_name})` : user.name
  return systemLogPartyLabel(props.log.user_id, props.nameOf)
})

const reference = computed(() => {
  const { reference_table: table, reference_id: id } = props.log
  if (!table && id === null) return '—'
  return `${table ?? '—'} #${id ?? '—'}`
})
</script>

<template>
  <BaseModal
    :title="`Log #${log.id}`"
    :badge="systemLogModuleLabel(log.module)"
    :badge-class="log.module === 'CASH' ? 'bg-amber-600' : 'bg-slate-600'"
    max-width="max-w-3xl"
    @close="emit('close')"
  >
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
      <div>
        <dt class="text-xs text-slate-500">Action</dt>
        <dd class="text-sm font-medium text-slate-900">{{ systemLogActionLabel(log.action) }}</dd>
        <dd class="font-mono text-[11px] text-slate-400">{{ log.action }}</dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">Time</dt>
        <dd class="text-sm tabular-nums text-slate-900">{{ formatDateTime(log.created_at) }}</dd>
        <dd v-if="log.created_at_human" class="text-xs text-slate-400">
          {{ log.created_at_human }}
        </dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">Performed by</dt>
        <dd class="text-sm text-slate-900">{{ performedBy }}</dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">Party</dt>
        <dd class="text-sm text-slate-900">{{ systemLogPartyLabel(log.customer_id, nameOf) }}</dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">Reference</dt>
        <dd class="font-mono text-xs text-slate-700">{{ reference }}</dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">IP address</dt>
        <dd class="font-mono text-xs text-slate-700">{{ log.ip_address || '—' }}</dd>
      </div>
    </dl>

    <div>
      <h3 class="mb-1 text-sm font-semibold text-slate-900">Description</h3>
      <p class="text-sm text-slate-700">{{ systemLogDescription(log.description, nameOf) }}</p>
    </div>

    <div v-if="metadataEntries.length > 0">
      <h3 class="mb-2 text-sm font-semibold text-slate-900">Summary</h3>
      <dl
        class="grid grid-cols-2 gap-x-6 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:grid-cols-3"
      >
        <div v-for="entry in metadataEntries" :key="entry.key" class="min-w-0">
          <dt class="text-xs text-slate-500">{{ entry.label }}</dt>
          <dd class="text-sm break-words tabular-nums text-slate-900">{{ entry.value }}</dd>
        </div>
      </dl>
    </div>

    <div>
      <h3 class="mb-2 text-sm font-semibold text-slate-900">Request data</h3>
      <pre
        v-if="payloadJson"
        class="max-h-80 overflow-auto rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 font-mono text-xs leading-relaxed text-slate-700"
        >{{ payloadJson }}</pre
      >
      <p v-else class="text-sm text-slate-500">No request data was recorded for this entry.</p>
      <p class="mt-1 text-xs text-slate-400">
        As submitted to the server; passwords and tokens are redacted.
      </p>
    </div>

    <div v-if="log.user_agent">
      <h3 class="mb-1 text-sm font-semibold text-slate-900">Device</h3>
      <p class="text-xs break-all text-slate-500">{{ log.user_agent }}</p>
    </div>
  </BaseModal>
</template>
