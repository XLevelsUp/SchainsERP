<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Eye, Pencil, TriangleAlert } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { remindersApi } from '@/lib/remindersApi'
import { userOptionLabel } from '@/lib/userLabel'
import { formatDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Reminder, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| View Notes — legacy Stock Details screen's per-head task/reminder list
|--------------------------------------------------------------------------
| ⚠️ BUILT AGAINST NO BACKEND. Confirmed 2026-09-25 (see types/reminder.ts
| and PENDING_WORK.md): there is no migration, model, controller or route
| for this anywhere in schainbackend. Every call in lib/remindersApi.ts
| 404s today. Built anyway, per house policy — UI ships ahead of the API it
| depends on, flagged on screen rather than held back — so the screen is
| ready the moment the backend exists, and so the visual/interaction spec
| (from the two legacy screenshots this was built from) is settled in one
| place instead of re-derived later.
|
| Layout: sits in StockManagementView's right column, above
| TransactionHistoryPanel, matching the legacy screen's placement opposite
| Head Stocks.
|
| Columns and actions are the legacy dialog's, not invented: Sno/Description/
| IsCompleted/Added By/Remainder At/Assign To/Is Viewed, a row checkbox, a
| bulk "Complete" button above the table, and per-row view (eye) / edit
| (pencil) — no delete button, because the legacy screen doesn't have one
| either. "Create new Remainder" collects only Description, Remainder Date
| and Assign To — no time field, even though the legacy table's Remainder At
| column displays one ("31-Jul-2026 10:52:36 AM"). Left as a date-only input
| here to match the dialog; where that time comes from is a question for
| whoever builds the backend, not something to guess at client-side.
|
| Viewing a row is modelled as the action that sets is_viewed — clicking the
| eye both opens the read-only detail and (once the API exists) marks it
| seen, matching the green "✓ Viewed" badge the legacy screen shows after
| someone has opened a note.
|--------------------------------------------------------------------------
*/

const props = defineProps<{ users: UserDetailListItem[] }>()

const auth = useAuthStore()
const toast = useToastStore()

const reminders = ref<Reminder[]>([])
const isLoading = ref(false)
const loadError = ref('')
const hasLoaded = ref(false)

const userOptions = computed(() =>
  props.users.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const userNameById = computed(() => {
  const map = new Map<number, string>()
  for (const u of props.users) map.set(u.id, u.full_name || u.name)
  return map
})

function addedByLabel(row: Reminder): string {
  return row.added_by_user?.name ?? userNameById.value.get(row.added_by) ?? `#${row.added_by}`
}
function assignToLabel(row: Reminder): string {
  return row.assign_to_user?.name ?? userNameById.value.get(row.assign_to) ?? `#${row.assign_to}`
}

async function loadReminders() {
  isLoading.value = true
  loadError.value = ''
  try {
    reminders.value = await remindersApi.list()
  } catch (err) {
    loadError.value =
      err instanceof ApiError
        ? `${err.message} — expected until the backend implements this.`
        : 'Failed to load notes.'
  } finally {
    isLoading.value = false
    hasLoaded.value = true
  }
}

onMounted(loadReminders)

// ---------------------------------------------------------------------------
// Row selection + bulk complete
// ---------------------------------------------------------------------------

const selectedIds = ref<Set<number>>(new Set())
const isCompleting = ref(false)

function toggleRow(id: number) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function toggleAll() {
  const next = new Set(selectedIds.value)
  const allSelected = reminders.value.length > 0 && reminders.value.every((r) => next.has(r.id))
  if (allSelected) reminders.value.forEach((r) => next.delete(r.id))
  else reminders.value.forEach((r) => next.add(r.id))
  selectedIds.value = next
}

async function completeSelected() {
  if (isCompleting.value || selectedIds.value.size === 0) return
  isCompleting.value = true
  const ids = [...selectedIds.value]
  try {
    await Promise.all(ids.map((id) => remindersApi.update(id, { is_completed: true })))
    for (const row of reminders.value) {
      if (ids.includes(row.id)) row.is_completed = true
    }
    selectedIds.value = new Set()
    toast.show(`Marked ${ids.length} note(s) complete.`, 'success')
  } catch (err) {
    toast.show(
      err instanceof ApiError
        ? `${err.message} — expected until the backend implements this.`
        : 'Failed to update notes.',
      'error',
    )
  } finally {
    isCompleting.value = false
  }
}

// ---------------------------------------------------------------------------
// Create / edit modal
// ---------------------------------------------------------------------------

const formMode = ref<'create' | 'edit' | null>(null)
const editingId = ref<number | null>(null)
const form = reactive({
  description: '',
  remainder_at: '',
  assign_to: null as number | null,
})
const formError = ref('')
const isSaving = ref(false)

function openCreate() {
  formMode.value = 'create'
  editingId.value = null
  form.description = ''
  form.remainder_at = ''
  form.assign_to = auth.user?.user_id ?? null
  formError.value = ''
}

function openEdit(row: Reminder) {
  formMode.value = 'edit'
  editingId.value = row.id
  form.description = row.description
  // Date-only input; drop any time component a real response might carry.
  form.remainder_at = row.remainder_at ? row.remainder_at.slice(0, 10) : ''
  form.assign_to = row.assign_to
  formError.value = ''
}

function closeForm() {
  formMode.value = null
  editingId.value = null
  formError.value = ''
}

async function saveForm() {
  if (isSaving.value) return
  if (!form.description.trim()) {
    formError.value = 'Description is required.'
    return
  }
  if (!form.remainder_at) {
    formError.value = 'Select a remainder date.'
    return
  }
  if (form.assign_to === null) {
    formError.value = 'Select who this is assigned to.'
    return
  }

  isSaving.value = true
  formError.value = ''
  const payload = {
    description: form.description.trim(),
    remainder_at: form.remainder_at,
    assign_to: form.assign_to,
  }
  try {
    if (formMode.value === 'create') {
      const created = await remindersApi.create(payload)
      reminders.value = [created, ...reminders.value]
      toast.show('Note created.', 'success')
    } else if (editingId.value !== null) {
      const updated = await remindersApi.update(editingId.value, payload)
      const index = reminders.value.findIndex((r) => r.id === editingId.value)
      if (index !== -1) reminders.value[index] = updated
      toast.show('Note saved.', 'success')
    }
    closeForm()
  } catch (err) {
    formError.value = err instanceof ApiError
      ? `${err.message} — expected until the backend implements this.`
      : 'Failed to save the note.'
  } finally {
    isSaving.value = false
  }
}

// ---------------------------------------------------------------------------
// View (read-only) — also marks is_viewed
// ---------------------------------------------------------------------------

const viewingRow = ref<Reminder | null>(null)

async function openView(row: Reminder) {
  viewingRow.value = row
  if (row.is_viewed) return
  try {
    const updated = await remindersApi.update(row.id, { is_viewed: true })
    row.is_viewed = updated.is_viewed
  } catch {
    // Non-fatal and expected right now — the row just keeps showing
    // "Not viewed" until the backend exists to persist it.
  }
}
function closeView() {
  viewingRow.value = null
}
</script>

<template>
  <BaseCard :padded="false">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-900">View Notes</h2>
      <button
        type="button"
        class="rounded-md bg-red-600 p-1 text-white hover:bg-red-700"
        aria-label="New note"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
      </button>
    </div>

    <div
      class="flex gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-900"
    >
      <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" />
      <p>
        This screen has no backend yet — nothing here can be saved or loaded until it exists.
        Built ahead of the API so the screen is ready the moment it ships.
      </p>
    </div>

    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
      <BaseButton
        variant="secondary"
        :disabled="selectedIds.size === 0 || isCompleting"
        @click="completeSelected"
      >
        {{ isCompleting ? 'Completing…' : 'Complete' }}
      </BaseButton>
      <span v-if="hasLoaded" class="text-xs text-slate-500">
        {{ reminders.length }} note{{ reminders.length === 1 ? '' : 's' }}
      </span>
    </div>

    <p v-if="loadError" class="px-4 py-3 text-sm text-red-700">{{ loadError }}</p>
    <div v-else-if="isLoading" class="px-4 py-8 text-center text-sm text-slate-500">Loading…</div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
            <th scope="col" class="w-8 px-3 py-2 text-left">
              <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                aria-label="Select all notes"
                :disabled="reminders.length === 0"
                @change="toggleAll"
              />
            </th>
            <th scope="col" class="px-3 py-2 text-left">Sno</th>
            <th scope="col" class="px-3 py-2 text-left">Description</th>
            <th scope="col" class="px-3 py-2 text-left">Is Completed</th>
            <th scope="col" class="px-3 py-2 text-left">Added By</th>
            <th scope="col" class="px-3 py-2 text-left">Remainder At</th>
            <th scope="col" class="px-3 py-2 text-left">Assign To</th>
            <th scope="col" class="px-3 py-2 text-left">Is Viewed</th>
            <th scope="col" class="px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="reminders.length === 0">
            <td colspan="9" class="px-4 py-8 text-center text-slate-500">
              {{ loadError ? 'Could not load notes.' : 'No notes yet.' }}
            </td>
          </tr>
          <tr
            v-for="(row, index) in reminders"
            :key="row.id"
            class="hover:bg-slate-50"
            :class="selectedIds.has(row.id) ? 'bg-brand-50/60' : ''"
          >
            <td class="px-3 py-2">
              <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                :aria-label="`Select note ${row.id}`"
                :checked="selectedIds.has(row.id)"
                @change="toggleRow(row.id)"
              />
            </td>
            <td class="px-3 py-2 text-slate-500">{{ index + 1 }}</td>
            <td class="px-3 py-2 text-slate-900">{{ row.description }}</td>
            <td class="px-3 py-2">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                :class="row.is_completed ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
              >
                {{ row.is_completed ? 'Yes' : 'No' }}
              </span>
            </td>
            <td class="px-3 py-2 text-slate-700">{{ addedByLabel(row) }}</td>
            <td class="px-3 py-2 whitespace-nowrap text-slate-500">
              {{ formatDateTime(row.remainder_at) }}
            </td>
            <td class="px-3 py-2 text-slate-700">{{ assignToLabel(row) }}</td>
            <td class="px-3 py-2">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                :class="row.is_viewed ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
              >
                {{ row.is_viewed ? '✓ Viewed' : 'Not viewed' }}
              </span>
            </td>
            <td class="px-3 py-2">
              <div class="flex justify-end gap-1">
                <button
                  type="button"
                  class="rounded-md bg-emerald-600 p-1.5 text-white hover:bg-emerald-700"
                  aria-label="View note"
                  @click="openView(row)"
                >
                  <Eye class="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  class="rounded-md bg-amber-500 p-1.5 text-white hover:bg-amber-600"
                  aria-label="Edit note"
                  @click="openEdit(row)"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </BaseCard>

  <BaseModal
    v-if="formMode"
    :title="formMode === 'create' ? 'Create new Remainder' : `Edit note #${editingId}`"
    max-width="max-w-md"
    @close="closeForm"
  >
    <form class="flex flex-col gap-5" @submit.prevent="saveForm">
      <BaseTextarea v-model="form.description" label="Description" required :rows="4" />
      <BaseInput v-model="form.remainder_at" type="date" label="Remainder Date" required />
      <BaseSelect
        v-model="form.assign_to"
        label="Assign to"
        required
        placeholder="Select…"
        :options="userOptions"
      />

      <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

      <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="closeForm">
          Close
        </BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Saving…' : 'Save' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>

  <BaseModal v-if="viewingRow" title="Note" max-width="max-w-md" @close="closeView">
    <div class="flex flex-col gap-4 text-sm">
      <p class="whitespace-pre-wrap text-slate-900">{{ viewingRow.description }}</p>
      <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-slate-500">
        <dt>Added by</dt>
        <dd class="text-slate-700">{{ addedByLabel(viewingRow) }}</dd>
        <dt>Assigned to</dt>
        <dd class="text-slate-700">{{ assignToLabel(viewingRow) }}</dd>
        <dt>Remainder at</dt>
        <dd class="text-slate-700">{{ formatDateTime(viewingRow.remainder_at) }}</dd>
        <dt>Completed</dt>
        <dd class="text-slate-700">{{ viewingRow.is_completed ? 'Yes' : 'No' }}</dd>
      </dl>
      <div class="flex justify-end border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" @click="closeView">Close</BaseButton>
      </div>
    </div>
  </BaseModal>
</template>
