<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import {
  Check,
  ChevronLeft,
  ChevronRight,
  Copy,
  Download,
  Eye,
  MessageCircle,
  Pencil,
  Plus,
  RefreshCw,
  Trash2,
  UserRound,
  X,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ConfirmPopover from '@/components/ui/ConfirmPopover.vue'
import DataTable from '@/components/ui/DataTable.vue'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/date'
import { isValidPhone, PHONE_ERROR, PHONE_LENGTH, phoneDigits } from '@/lib/phone'
import { numbersText, phoneBookCsv, whatsappUrl } from '@/lib/phoneBook'
import { isRoleActive } from '@/lib/rolesApi'
import { useToastStore } from '@/stores/toast'
import type { DataTableColumn, PhoneBookContact, PhoneBookFormValues, Role } from '@/types'
import { phoneBookApi, rolesApi } from '../lib/resources'

/*
|--------------------------------------------------------------------------
| Admin console — Phone Book (agreed 2026-11-24, the legacy Phone Books)
|--------------------------------------------------------------------------
| The phone book is its own list of contacts (name, phone, role, active),
| not the users table: every user was copied in once and each user the
| console creates is added (backend PhoneBookSync). Editing or deleting a
| contact never changes a user. The app shows the same list, read-only.
|
| The list is small and comes back whole, so the column filters and paging
| (20 a page, or All) run here, like the Users page.
|
| Toolbar: WhatsApp copies the ticked contacts' numbers for a WhatsApp
| broadcast list (sending to many needs the paid Business API); Export
| saves the filtered list as CSV. Each row's WhatsApp button opens a chat.
| Phone numbers: exactly 10 digits and unique in the phone book.
|--------------------------------------------------------------------------
*/

const PAGE_SIZE = 20

const toast = useToastStore()

const contacts = ref<PhoneBookContact[]>([])
const roles = ref<Role[]>([])
const isLoading = ref(false)
const loadError = ref('')

async function loadContacts() {
  if (isLoading.value) return
  isLoading.value = true
  loadError.value = ''
  try {
    contacts.value = await phoneBookApi.listAll()
    // Drop ticks for contacts that are gone.
    const ids = new Set(contacts.value.map((c) => c.id))
    selectedIds.value = new Set([...selectedIds.value].filter((id) => ids.has(id)))
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the phone book.'
  } finally {
    isLoading.value = false
  }
}

async function loadRoles() {
  try {
    roles.value = [...(await rolesApi.list())].sort((a, b) => a.id - b.id)
  } catch {
    // Non-fatal: the role pickers are just empty; Refresh tries again.
  }
}

function refresh() {
  void loadContacts()
  void loadRoles()
}

onMounted(refresh)

// ---- filters & paging ------------------------------------------------------

type StatusFilter = 'all' | 'active' | 'inactive'

// Like the legacy screen, every contact shows until a filter is set.
const filters = reactive({ name: '', phone: '', roleId: '', status: 'all' as StatusFilter })

const roleFilterOptions = computed(() => [
  { value: '', label: 'All roles' },
  ...roles.value.map((r) => ({ value: String(r.id), label: r.role })),
])
const statusOptions: { value: StatusFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

const filtered = computed(() => {
  const name = filters.name.trim().toLowerCase()
  const phone = filters.phone.trim()
  return contacts.value.filter((c) => {
    if (name && !c.name.toLowerCase().includes(name)) return false
    if (phone && !c.phone_no.includes(phone)) return false
    if (filters.roleId && String(c.role_id ?? '') !== filters.roleId) return false
    if (filters.status !== 'all' && c.is_active !== (filters.status === 'active')) return false
    return true
  })
})

const isFiltered = computed(
  () => !!filters.name.trim() || !!filters.phone.trim() || !!filters.roleId || filters.status !== 'all',
)

const page = ref(1)
const showAll = ref(false)
const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)))
const offset = computed(() => (showAll.value ? 0 : (page.value - 1) * PAGE_SIZE))
const rangeTo = computed(() =>
  showAll.value ? filtered.value.length : Math.min(page.value * PAGE_SIZE, filtered.value.length),
)

watch(filters, () => (page.value = 1), { deep: true })
watch(lastPage, (last) => {
  if (page.value > last) page.value = last
})

// ---- selection (for the WhatsApp copy) -----------------------------------------

const selectedIds = ref(new Set<number>())

interface PhoneBookRow extends PhoneBookContact {
  position: number
  selected: boolean
}

const pageRows = computed<PhoneBookRow[]>(() => {
  const rows = showAll.value ? filtered.value : filtered.value.slice(offset.value, offset.value + PAGE_SIZE)
  return rows.map((c, i) => ({ ...c, position: offset.value + i + 1, selected: selectedIds.value.has(c.id) }))
})

const selectedContacts = computed(() => contacts.value.filter((c) => selectedIds.value.has(c.id)))
const allFilteredSelected = computed(
  () => filtered.value.length > 0 && filtered.value.every((c) => selectedIds.value.has(c.id)),
)

function setSelected(id: number, on: boolean) {
  const next = new Set(selectedIds.value)
  if (on) next.add(id)
  else next.delete(id)
  selectedIds.value = next
}

// The header box ticks (or clears) every contact the filters show.
function setAllSelected(on: boolean) {
  const next = new Set(selectedIds.value)
  for (const c of filtered.value) {
    if (on) next.add(c.id)
    else next.delete(c.id)
  }
  selectedIds.value = next
}

async function copySelectedNumbers() {
  const text = numbersText(selectedContacts.value)
  if (!text) return
  const count = text.split('\n').length
  try {
    await navigator.clipboard.writeText(text)
    toast.show(`${count} number${count === 1 ? '' : 's'} copied. Paste them into a WhatsApp broadcast list.`, 'success')
  } catch {
    toast.show("Couldn't copy: the browser blocked the clipboard.", 'error')
  }
}

// ---- export ----------------------------------------------------------------

function exportCsv() {
  // A BOM so Excel reads the file as UTF-8.
  const blob = new Blob(['﻿' + phoneBookCsv(filtered.value)], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  try {
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `phone-book-${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
  } finally {
    URL.revokeObjectURL(url)
  }
}

// ---- table ----------------------------------------------------------------

const columns: DataTableColumn<PhoneBookRow>[] = [
  { key: 'selected', label: 'Select' },
  { key: 'position', label: '#' },
  { key: 'name', label: 'Name' },
  { key: 'phone_no', label: 'Phone no' },
  { key: 'profile_image_url', label: 'Image' },
  { key: 'role_id', label: 'Role' },
  { key: 'is_active', label: 'Is active' },
  { key: 'id', label: '' },
]

function roleName(contact: PhoneBookContact): string {
  return contact.role?.role_name ?? roles.value.find((r) => r.id === contact.role_id)?.role ?? '—'
}

// Pictures whose file is missing fall back to initials.
const brokenImages = ref(new Set<number>())
function markBroken(id: number) {
  brokenImages.value = new Set(brokenImages.value).add(id)
}

function initials(name: string): string {
  return (
    name
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part[0] ?? '')
      .join('')
      .toUpperCase() || '?'
  )
}

// ---- status ----------------------------------------------------------------

const togglingId = ref<number | null>(null)

async function toggleActive(contact: PhoneBookContact) {
  if (togglingId.value !== null) return
  togglingId.value = contact.id
  try {
    const saved = await phoneBookApi.setActive(contact.id, !contact.is_active)
    replaceContact(saved)
    toast.show(`${saved.name} ${saved.is_active ? 'activated' : 'deactivated'}.`, 'success')
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to change the status.', 'error')
  } finally {
    togglingId.value = null
  }
}

function replaceContact(saved: PhoneBookContact) {
  const index = contacts.value.findIndex((c) => c.id === saved.id)
  if (index >= 0) contacts.value.splice(index, 1, saved)
}

// ---- create / edit modal ---------------------------------------------------------

// null = closed; { id: null } = new contact; { id } = editing.
const formTarget = ref<{ id: number | null } | null>(null)
const form = reactive<PhoneBookFormValues>({ name: '', phone_no: '', role_id: null, is_active: true })
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const isSaving = ref(false)

// Active roles, plus the contact's own role if it has been switched off.
const roleOptions = computed(() =>
  roles.value
    .filter((r) => isRoleActive(r) || r.id === form.role_id)
    .map((r) => ({ value: r.id, label: r.role })),
)

function clearErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''
}

function openCreate() {
  Object.assign(form, { name: '', phone_no: '', role_id: null, is_active: true })
  clearErrors()
  formTarget.value = { id: null }
}

function openEdit(contact: PhoneBookContact) {
  Object.assign(form, {
    name: contact.name,
    phone_no: contact.phone_no,
    role_id: contact.role_id,
    is_active: contact.is_active,
  })
  clearErrors()
  viewing.value = null
  formTarget.value = { id: contact.id }
}

function validate(): boolean {
  clearErrors()
  const name = form.name.trim()
  if (!name) fieldErrors.name = 'Name is required.'
  else if (name.length > 55) fieldErrors.name = 'Name must be 55 characters or fewer.'
  if (!form.phone_no.trim()) fieldErrors.phone_no = 'Phone number is required.'
  else if (!isValidPhone(form.phone_no)) fieldErrors.phone_no = PHONE_ERROR
  if (form.role_id === null) fieldErrors.role_id = 'Role is required.'
  return Object.keys(fieldErrors).length === 0
}

async function handleSave() {
  if (isSaving.value || !formTarget.value || !validate()) return
  isSaving.value = true
  try {
    const id = formTarget.value.id
    const saved = id === null ? await phoneBookApi.create(form) : await phoneBookApi.update(id, form)
    formTarget.value = null
    toast.show(`${saved.name} ${id === null ? 'added to' : 'updated in'} the phone book.`, 'success')
    if (id === null) await loadContacts()
    else replaceContact(saved)
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      for (const [key, messages] of Object.entries(err.errors ?? {})) fieldErrors[key] = messages[0] ?? ''
    } else {
      formError.value = 'Failed to save the contact.'
    }
  } finally {
    isSaving.value = false
  }
}

// ---- view / delete ----------------------------------------------------------

const viewing = ref<PhoneBookContact | null>(null)
const deletingId = ref<number | null>(null)

async function handleDelete(contact: PhoneBookContact) {
  if (deletingId.value !== null) return
  deletingId.value = contact.id
  try {
    await phoneBookApi.remove(contact.id)
    contacts.value = contacts.value.filter((c) => c.id !== contact.id)
    setSelected(contact.id, false)
    toast.show(`${contact.name} removed from the phone book.`, 'success')
  } catch (err) {
    toast.show(err instanceof ApiError ? err.message : 'Failed to delete the contact.', 'error')
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Phone Book"
      description="Everyone's phone numbers. New users are added automatically; changing a contact never changes the user."
    >
      <template #actions>
        <BaseButton
          variant="secondary"
          class="shrink-0 whitespace-nowrap"
          :icon="Copy"
          :disabled="!selectedContacts.length"
          :title="selectedContacts.length ? 'Copy the ticked numbers for a WhatsApp broadcast list' : 'Tick contacts first'"
          @click="copySelectedNumbers"
        >
          WhatsApp{{ selectedContacts.length ? ` (${selectedContacts.length})` : '' }}
        </BaseButton>
        <BaseButton variant="secondary" class="shrink-0" :icon="Download" :disabled="!filtered.length" @click="exportCsv">
          Export
        </BaseButton>
        <BaseButton variant="secondary" class="shrink-0" :icon="RefreshCw" :disabled="isLoading" @click="refresh">
          Refresh
        </BaseButton>
        <BaseButton class="shrink-0 whitespace-nowrap" :icon="Plus" @click="openCreate">New contact</BaseButton>
      </template>
    </PageHeader>

    <div class="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <BaseInput v-model="filters.name" type="search" size="sm" placeholder="Name…" aria-label="Filter by name" />
      <BaseInput
        v-model="filters.phone"
        type="search"
        size="sm"
        inputmode="numeric"
        placeholder="Phone no…"
        aria-label="Filter by phone"
      />
      <BaseSelect v-model="filters.roleId" size="sm" aria-label="Filter by role" :options="roleFilterOptions" />
      <BaseSelect v-model="filters.status" size="sm" aria-label="Filter by status" :options="statusOptions" />
    </div>

    <div
      v-if="loadError"
      class="mb-4 flex items-center justify-between gap-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      <span>{{ loadError }}</span>
      <button type="button" class="font-medium underline" @click="loadContacts">Try again</button>
    </div>

    <div
      v-if="isLoading && !contacts.length"
      class="rounded-lg border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
    >
      Loading the phone book…
    </div>

    <template v-else>
      <DataTable
        :columns="columns"
        :rows="pageRows"
        size="sm"
        :empty-message="isFiltered ? 'No contacts match these filters.' : 'No contacts yet. Add the first one.'"
      >
        <template #header-selected>
          <input
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            :checked="allFilteredSelected"
            :disabled="!filtered.length"
            aria-label="Select every contact shown"
            @change="setAllSelected(($event.target as HTMLInputElement).checked)"
          />
        </template>

        <template #selected="{ row }">
          <input
            type="checkbox"
            class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            :checked="row.selected"
            :aria-label="`Select ${row.name}`"
            @change="setSelected(row.id, ($event.target as HTMLInputElement).checked)"
          />
        </template>

        <template #position="{ value }">
          <span class="text-slate-500 tabular-nums">{{ value }}</span>
        </template>

        <template #name="{ value }">
          <span class="font-medium text-slate-900">{{ value }}</span>
        </template>

        <template #phone_no="{ row }">
          <a :href="`tel:${row.phone_no}`" class="tabular-nums hover:text-brand-700 hover:underline">{{ row.phone_no }}</a>
          <span
            v-if="!isValidPhone(row.phone_no)"
            class="ml-1.5 rounded bg-amber-50 px-1 py-0.5 text-[11px] font-medium text-amber-700"
            title="Not 10 digits: correct it on the next edit"
          >
            check
          </span>
        </template>

        <template #profile_image_url="{ row }">
          <img
            v-if="row.profile_image_url && !brokenImages.has(row.id)"
            :src="row.profile_image_url"
            :alt="row.name"
            class="h-7 w-7 rounded-full object-cover ring-1 ring-slate-200"
            @error="markBroken(row.id)"
          />
          <span
            v-else
            class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-500"
            aria-hidden="true"
          >
            {{ initials(row.name) }}
          </span>
        </template>

        <template #role_id="{ row }">
          <span class="text-xs font-semibold tracking-wide text-slate-700 uppercase">{{ roleName(row) }}</span>
        </template>

        <template #is_active="{ row }">
          <ConfirmPopover
            :message="row.is_active ? `Mark ${row.name} inactive?` : `Mark ${row.name} active?`"
            :confirm-label="row.is_active ? 'Deactivate' : 'Activate'"
            @confirm="toggleActive(row)"
          >
            <template #default="{ toggle }">
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium disabled:opacity-50"
                :class="row.is_active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                :disabled="togglingId === row.id"
                :title="row.is_active ? 'Click to deactivate' : 'Click to activate'"
                @click="toggle"
              >
                <Check v-if="row.is_active" class="h-3.5 w-3.5" aria-hidden="true" />
                <X v-else class="h-3.5 w-3.5" aria-hidden="true" />
                {{ row.is_active ? 'Active' : 'Inactive' }}
              </button>
            </template>
          </ConfirmPopover>
        </template>

        <template #id="{ row }">
          <div class="flex items-center justify-end gap-1">
            <a
              v-if="whatsappUrl(row.phone_no)"
              :href="whatsappUrl(row.phone_no)!"
              target="_blank"
              rel="noopener noreferrer"
              class="rounded-md p-1.5 text-emerald-600 hover:bg-emerald-50"
              :aria-label="`WhatsApp ${row.name}`"
              title="Open a WhatsApp chat"
            >
              <MessageCircle class="h-4 w-4" />
            </a>
            <span v-else class="p-1.5 text-slate-200" title="Not a 10-digit number" aria-hidden="true">
              <MessageCircle class="h-4 w-4" />
            </span>
            <button
              type="button"
              class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              :aria-label="`View ${row.name}`"
              @click="viewing = row"
            >
              <Eye class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              :aria-label="`Edit ${row.name}`"
              @click="openEdit(row)"
            >
              <Pencil class="h-4 w-4" />
            </button>
            <ConfirmPopover
              :message="`Remove ${row.name} from the phone book? The user account, if any, stays.`"
              confirm-label="Delete"
              @confirm="handleDelete(row)"
            >
              <template #default="{ toggle }">
                <button
                  type="button"
                  class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50"
                  :aria-label="`Delete ${row.name}`"
                  :disabled="deletingId === row.id"
                  @click="toggle"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </template>
            </ConfirmPopover>
          </div>
        </template>
      </DataTable>

      <div v-if="filtered.length" class="mt-3 flex items-center justify-between text-xs text-slate-500">
        <span class="tabular-nums">
          Showing {{ offset + 1 }}–{{ rangeTo }} of {{ filtered.length }} contacts
          <template v-if="selectedContacts.length"> · {{ selectedContacts.length }} ticked</template>
        </span>
        <div class="flex items-center gap-2">
          <button type="button" class="font-medium text-brand-600 hover:text-brand-700" @click="showAll = !showAll">
            {{ showAll ? 'Show 20 a page' : 'Show all' }}
          </button>
          <template v-if="!showAll && lastPage > 1">
            <button
              type="button"
              class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="page === 1"
              aria-label="Previous page"
              @click="page--"
            >
              <ChevronLeft class="h-4 w-4" />
            </button>
            <span class="tabular-nums">Page {{ page }} of {{ lastPage }}</span>
            <button
              type="button"
              class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="page === lastPage"
              aria-label="Next page"
              @click="page++"
            >
              <ChevronRight class="h-4 w-4" />
            </button>
          </template>
        </div>
      </div>
    </template>

    <!-- Create / edit -->
    <BaseModal
      v-if="formTarget"
      :title="formTarget.id === null ? 'New contact' : 'Edit contact'"
      max-width="max-w-md"
      @close="formTarget = null"
    >
      <form class="flex flex-col gap-4" novalidate @submit.prevent="handleSave">
        <BaseInput id="pb_name" v-model="form.name" label="Name" required :maxlength="55" :error="fieldErrors.name" />
        <BaseInput
          id="pb_phone"
          v-model="form.phone_no"
          label="Phone no"
          required
          inputmode="numeric"
          :maxlength="PHONE_LENGTH"
          placeholder="10 digits"
          :sanitize="phoneDigits"
          :error="fieldErrors.phone_no"
        />
        <BaseSelect
          id="pb_role"
          v-model="form.role_id"
          label="Role"
          required
          placeholder="Select a role…"
          :options="roleOptions"
          :reload="loadRoles"
          :error="fieldErrors.role_id"
        />
        <BaseCheckbox id="pb_active" v-model="form.is_active" label="Active" />

        <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
          <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="formTarget = null">
            Close
          </BaseButton>
          <BaseButton type="submit" :disabled="isSaving">{{ isSaving ? 'Saving…' : 'Save' }}</BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- View -->
    <BaseModal v-if="viewing" :title="viewing.name" max-width="max-w-md" @close="viewing = null">
      <div class="mb-4 flex items-center gap-3">
        <img
          v-if="viewing.profile_image_url && !brokenImages.has(viewing.id)"
          :src="viewing.profile_image_url"
          :alt="viewing.name"
          class="h-14 w-14 rounded-full object-cover ring-1 ring-slate-200"
          @error="markBroken(viewing.id)"
        />
        <span v-else class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
          <UserRound class="h-6 w-6" aria-hidden="true" />
        </span>
        <div>
          <p class="font-medium text-slate-900">{{ viewing.name }}</p>
          <p class="text-sm text-slate-500 tabular-nums">{{ viewing.phone_no }}</p>
        </div>
      </div>
      <dl class="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-3 text-sm">
        <dt class="text-slate-500">Role</dt>
        <dd class="text-slate-900">{{ roleName(viewing) }}</dd>
        <dt class="text-slate-500">Status</dt>
        <dd class="text-slate-900">{{ viewing.is_active ? 'Active' : 'Inactive' }}</dd>
        <dt class="text-slate-500">From a user</dt>
        <dd class="text-slate-900">{{ viewing.user_id ? 'Yes' : 'No — added here' }}</dd>
        <dt class="text-slate-500">Added</dt>
        <dd class="text-slate-900">{{ formatDateTime(viewing.created_at) }}</dd>
        <dt class="text-slate-500">Updated</dt>
        <dd class="text-slate-900">{{ formatDateTime(viewing.updated_at) }}</dd>
      </dl>
      <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
        <BaseButton variant="secondary" @click="viewing = null">Close</BaseButton>
        <BaseButton :icon="Pencil" @click="openEdit(viewing)">Edit</BaseButton>
      </div>
    </BaseModal>
  </div>
</template>
