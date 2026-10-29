<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Eye, Pencil, ChevronDown } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { remindersApi } from '@/lib/remindersApi'
import { userOptionLabel } from '@/lib/userLabel'
// Date-only, not formatDateTime: remainder_at is a DATE column, so rendering
// it with a time would show a 00:00 the operator never entered.
import { formatDateOnly } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Reminder, UserDetailListItem } from '@/types'

/*
|--------------------------------------------------------------------------
| View Notes — legacy Stock Details screen's per-head task/reminder list
|--------------------------------------------------------------------------
| LIVE as of PR #50 (2026-10-08). This was built against no backend at all
| on 2026-09-25 and shipped behind a warning banner, per house policy. The
| `reminders` table, model, controller and `apiResource('reminders')` all
| landed in PR #50, so the banner is gone and every action below now
| persists. The bet paid off: the backend schema matched the proposed shape
| field for field, and only the API adapter needed changing.
|
| Three contract quirks are handled in lib/remindersApi.ts, not here —
| this panel sees clean, normalised rows. Read that file before changing
| anything about how data gets in or out:
|   - the endpoint returns bare JSON, not the { success, data } envelope
|   - added_by/assign_to come back as full user objects from index/show but
|     as plain integers from store/update
|   - remainder_at arrives as a UTC instant that reads a day early if sliced
|
| Known backend gaps, both flagged: index() is unscoped (every user sees
| every note — filtered client-side below) and unpaginated, and store/update
| return no relations, so a freshly created row has ids but no names. The
| name lookup below covers that.
|
| Layout: sits in StockManagementView's right column, above
| TransactionHistoryPanel, matching the legacy screen's placement opposite
| Head Stocks.
|
| Collapsed by default (2026-09-30 request): expanded, the banner + Complete
| row + table made this the tallest thing in either column, pushing
| Transaction History down far past where Head Stocks starts on the left —
| the two columns no longer read as one page. Collapsed to a single header
| bar (title + note count + a chevron) fixes that; expanding is one click
| and the "+ New note" action still works without expanding first.
|
| This panel and StockManagementView's Clear button are meant to line up
| with HeadStockSummaryPanel/TransactionHistoryPanel below them, but that
| no longer depends on this component at all — StockManagementView puts
| this panel and Clear in the same CSS grid row, which is what actually
| guarantees the row below starts level regardless of either one's height.
| An earlier version of this tried to get the same result by matching
| Tailwind padding between two unrelated components — fragile, and it
| didn't reliably work. Nothing here needs to stay in sync with Clear's
| styling any more; see StockManagementView's own comment for the real
| mechanism.
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

const props = defineProps<{ users: UserDetailListItem[]; reloadUsers?: () => unknown }>()

const auth = useAuthStore()
const toast = useToastStore()

// Collapsed by default — see the header comment for why.
const isExpanded = ref(false)

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

// Three sources, in order of trust: the name the API nested (index/show
// only), this screen's own user list (covers store/update, which return no
// relations), then the bare id so a row is never blank.
function userLabel(id: number | null, nested: string | null): string {
  if (nested) return nested
  if (id === null) return '—'
  return userNameById.value.get(id) ?? `#${id}`
}

function addedByLabel(row: Reminder): string {
  return userLabel(row.added_by, row.added_by_name)
}
function assignToLabel(row: Reminder): string {
  return userLabel(row.assign_to, row.assign_to_name)
}

async function loadReminders() {
  isLoading.value = true
  loadError.value = ''
  try {
    reminders.value = await remindersApi.list()
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load notes.'
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
    toast.show(err instanceof ApiError ? err.message : 'Failed to update notes.', 'error')
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
  // So the operator lands on the table their new row will appear in,
  // rather than saving into a still-collapsed panel.
  isExpanded.value = true
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
  // Already "YYYY-MM-DD" — remindersApi undoes the UTC shift on the way in,
  // so this feeds <input type="date"> directly.
  form.remainder_at = row.remainder_at ?? ''
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
    formError.value = err instanceof ApiError ? err.message : 'Failed to save the note.'
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
    // Non-fatal on purpose: the operator opened the note and should see it,
    // whether or not the "seen" flag made it to the server. The row keeps
    // showing "Not viewed" and the next open retries.
  }
}
function closeView() {
  viewingRow.value = null
}
</script>

<template>
  <BaseCard :padded="false">
    <!--
      A plain div, not a button — the "+" below is its own real button, and
      a <button> cannot validly contain another focusable control. The
      toggle and the "+" are two sibling buttons instead, each with their
      own hit area, which also means the keyboard/screen-reader story is
      correct for free (no click.stop juggling to keep one from firing the
      other).
    -->
    <div
      class="flex items-center justify-between px-4 py-2"
      :class="isExpanded ? 'border-b border-slate-200' : ''"
    >
      <button
        type="button"
        class="flex min-w-0 flex-1 items-center gap-2 rounded text-left focus-visible:outline-2 focus-visible:outline-gold-500"
        :aria-expanded="isExpanded"
        aria-label="Toggle View Notes"
        @click="isExpanded = !isExpanded"
      >
        <h2 class="text-sm font-semibold text-slate-900">View Notes</h2>
        <span
          v-if="hasLoaded && reminders.length > 0"
          class="inline-flex min-w-5 items-center justify-center rounded-full bg-slate-100 px-1.5 py-0.5 font-figure text-xs font-medium text-slate-600 tabular-nums"
        >
          {{ reminders.length }}
        </span>
        <ChevronDown
          class="h-4 w-4 text-slate-400 motion-safe:transition-transform"
          :class="isExpanded ? 'rotate-180' : ''"
        />
      </button>
      <button
        type="button"
        class="ml-2 flex shrink-0 items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gold-500"
        aria-label="New note"
        @click="openCreate"
      >
        <Plus class="h-3.5 w-3.5" /> New note
      </button>
    </div>

    <template v-if="isExpanded">
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/60 px-4 py-2">
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
          <thead>
            <tr
              class="border-b border-slate-100 font-figure text-[11px] font-semibold tracking-[0.06em] whitespace-nowrap text-slate-500 uppercase"
            >
              <th scope="col" class="w-8 px-3 py-2 text-left">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 accent-ink-900"
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
              :class="selectedIds.has(row.id) ? 'bg-gold-50' : 'hover:bg-slate-50'"
            >
              <td class="px-3 py-1.5">
                <input
                  type="checkbox"
                  class="h-3.5 w-3.5 rounded border-slate-300 accent-ink-900"
                  :aria-label="`Select note ${row.id}`"
                  :checked="selectedIds.has(row.id)"
                  @change="toggleRow(row.id)"
                />
              </td>
              <td class="px-3 py-1.5 font-figure tabular-nums text-slate-400">{{ index + 1 }}</td>
              <td class="px-3 py-1.5 text-slate-900">{{ row.description }}</td>
              <td class="px-3 py-1.5">
                <span
                  class="inline-flex rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap"
                  :class="row.is_completed ? 'bg-inflow-50 text-inflow-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ row.is_completed ? 'Yes' : 'No' }}
                </span>
              </td>
              <td class="px-3 py-1.5 text-slate-700">{{ addedByLabel(row) }}</td>
              <td class="px-3 py-1.5 whitespace-nowrap font-figure tabular-nums text-slate-600">
                {{ formatDateOnly(row.remainder_at) }}
              </td>
              <td class="px-3 py-1.5 text-slate-700">{{ assignToLabel(row) }}</td>
              <td class="px-3 py-1.5">
                <span
                  class="inline-flex rounded px-1.5 py-0.5 text-xs font-medium whitespace-nowrap"
                  :class="row.is_viewed ? 'bg-inflow-50 text-inflow-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ row.is_viewed ? '✓ Viewed' : 'Not viewed' }}
                </span>
              </td>
              <td class="px-3 py-1.5">
                <div class="flex justify-end gap-0.5">
                  <button
                    type="button"
                    class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink-900"
                    aria-label="View note"
                    title="View note"
                    @click="openView(row)"
                  >
                    <Eye class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink-900"
                    aria-label="Edit note"
                    title="Edit note"
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
    </template>
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
        :reload="reloadUsers"
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
        <dd class="text-slate-700">{{ formatDateOnly(viewingRow.remainder_at) }}</dd>
        <dt>Completed</dt>
        <dd class="text-slate-700">{{ viewingRow.is_completed ? 'Yes' : 'No' }}</dd>
      </dl>
      <div class="flex justify-end border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" @click="closeView">Close</BaseButton>
      </div>
    </div>
  </BaseModal>
</template>
