<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Bell, Plus, X, Check, Repeat, CalendarClock, CalendarDays } from 'lucide-vue-next'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { formatDateOnly } from '@/lib/date'

// UI-only feature. Mock data held in memory — no backend calls.
type Recurrence = 'DAILY' | 'MONTHLY' | 'ONE_TIME'

interface Reminder {
  id: number
  title: string
  due_date: string // YYYY-MM-DD
  recurrence: Recurrence
  acknowledged: boolean
}

const today = new Date().toISOString().slice(0, 10)

let nextId = 5
const reminders = ref<Reminder[]>([
  { id: 1, title: 'Pay studio rent', due_date: today, recurrence: 'MONTHLY', acknowledged: false },
  { id: 2, title: 'Check nightly backups', due_date: today, recurrence: 'DAILY', acknowledged: false },
  { id: 3, title: 'Renew equipment insurance', due_date: '2026-08-15', recurrence: 'ONE_TIME', acknowledged: false },
  { id: 4, title: 'Submit GST filing', due_date: '2026-08-20', recurrence: 'MONTHLY', acknowledged: false },
])

const recurrenceMeta: Record<Recurrence, { label: string; icon: typeof Repeat; classes: string }> = {
  DAILY: { label: 'Daily', icon: Repeat, classes: 'bg-sky-50 text-sky-700' },
  MONTHLY: { label: 'Monthly', icon: CalendarDays, classes: 'bg-violet-50 text-violet-700' },
  ONE_TIME: { label: 'One-time', icon: CalendarClock, classes: 'bg-slate-100 text-slate-600' },
}

const dueTodayCount = computed(
  () => reminders.value.filter((r) => r.due_date === today && !r.acknowledged).length,
)

// Sort: due-today & unacknowledged first, then by date.
const sortedReminders = computed(() =>
  [...reminders.value].sort((a, b) => {
    const aDue = a.due_date === today && !a.acknowledged ? 0 : 1
    const bDue = b.due_date === today && !b.acknowledged ? 0 : 1
    if (aDue !== bDue) return aDue - bDue
    return a.due_date.localeCompare(b.due_date)
  }),
)

function isDueToday(r: Reminder) {
  return r.due_date === today
}

function formatDate(date: string) {
  if (date === today) return 'Today'
  return formatDateOnly(date)
}

function acknowledge(r: Reminder) {
  r.acknowledged = true
}

function remove(id: number) {
  reminders.value = reminders.value.filter((r) => r.id !== id)
}

// ----- add form -----
const isAdding = ref(false)
const form = reactive<{ title: string; due_date: string; recurrence: Recurrence }>({
  title: '',
  due_date: today,
  recurrence: 'ONE_TIME',
})
const formError = ref('')

function openAdd() {
  form.title = ''
  form.due_date = today
  form.recurrence = 'ONE_TIME'
  formError.value = ''
  isAdding.value = true
}

function saveReminder() {
  if (!form.title.trim()) {
    formError.value = 'Title is required.'
    return
  }
  reminders.value.push({
    id: nextId++,
    title: form.title.trim(),
    due_date: form.due_date,
    recurrence: form.recurrence,
    acknowledged: false,
  })
  isAdding.value = false
}
</script>

<template>
  <BaseCard>
    <div class="mb-4 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="relative inline-flex">
          <Bell class="h-5 w-5 text-brand-600" aria-hidden="true" />
          <span
            v-if="dueTodayCount > 0"
            class="absolute -top-1.5 -right-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
            :aria-label="`${dueTodayCount} due today`"
          >
            {{ dueTodayCount }}
          </span>
        </span>
        <h2 class="text-sm font-semibold tracking-wide text-slate-900 uppercase">Reminders</h2>
      </div>
      <BaseButton variant="secondary" :icon="Plus" @click="openAdd">New</BaseButton>
    </div>

    <!-- Add form -->
    <div v-if="isAdding" class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
      <div class="flex flex-col gap-3">
        <BaseInput
          id="reminder-title"
          v-model="form.title"
          label="Title"
          placeholder="e.g. Pay studio rent"
          :error="formError"
        />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <BaseInput id="reminder-date" v-model="form.due_date" type="date" label="Due date" />
          <BaseSelect
            id="reminder-recurrence"
            v-model="form.recurrence"
            label="Repeats"
            :options="[
              { value: 'ONE_TIME', label: 'One-time' },
              { value: 'DAILY', label: 'Daily' },
              { value: 'MONTHLY', label: 'Monthly' },
            ]"
          />
        </div>
        <div class="flex items-center gap-2">
          <BaseButton @click="saveReminder">Add reminder</BaseButton>
          <BaseButton variant="ghost" @click="isAdding = false">Cancel</BaseButton>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <p v-if="reminders.length === 0" class="py-6 text-center text-sm text-slate-500">
      No reminders yet — create one to get a popup on its due day.
    </p>

    <!-- List -->
    <ul v-else class="flex flex-col divide-y divide-slate-100">
      <li
        v-for="r in sortedReminders"
        :key="r.id"
        class="flex items-center gap-3 py-3"
        :class="r.acknowledged ? 'opacity-55' : ''"
      >
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span
              class="truncate text-sm font-medium text-slate-900"
              :class="r.acknowledged ? 'line-through' : ''"
            >
              {{ r.title }}
            </span>
            <span
              v-if="isDueToday(r) && !r.acknowledged"
              class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600"
            >
              Due today
            </span>
          </div>
          <div class="mt-1 flex items-center gap-2">
            <span
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
              :class="recurrenceMeta[r.recurrence].classes"
            >
              <component :is="recurrenceMeta[r.recurrence].icon" class="h-3 w-3" aria-hidden="true" />
              {{ recurrenceMeta[r.recurrence].label }}
            </span>
            <span class="text-xs text-slate-400">{{ formatDate(r.due_date) }}</span>
          </div>
        </div>

        <button
          v-if="isDueToday(r) && !r.acknowledged"
          type="button"
          class="rounded-md p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
          aria-label="Dismiss for today"
          title="Dismiss for today"
          @click="acknowledge(r)"
        >
          <Check class="h-4 w-4" />
        </button>
        <button
          type="button"
          class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
          aria-label="Delete reminder"
          title="Delete"
          @click="remove(r.id)"
        >
          <X class="h-4 w-4" />
        </button>
      </li>
    </ul>
  </BaseCard>
</template>