import { api, type ApiResponse } from './api'
import type { Reminder, ReminderCreate, ReminderUpdate } from '@/types'

// ⚠️ SPECULATIVE — no such route exists yet. See types/reminder.ts and the
// PENDING_WORK.md backend ask. Every call below 404s until the backend team
// implements a matching `apiResource('reminders')`. Written now, against the
// pattern every other resource in this app follows, so wiring the real thing
// up later is a rename rather than new code — and so ViewNotesPanel.vue has
// something concrete to call instead of hand-rolling mock state that would
// need throwing away later.
const RESOURCE = '/reminders'

export const remindersApi = {
  list: () => api.get<ApiResponse<Reminder[]>>(RESOURCE).then((r) => r.data),

  create: (payload: ReminderCreate) =>
    api.post<ApiResponse<Reminder>>(RESOURCE, payload).then((r) => r.data),

  update: (id: number, payload: ReminderUpdate) =>
    api.put<ApiResponse<Reminder>>(`${RESOURCE}/${id}`, payload).then((r) => r.data),

  remove: (id: number) => api.delete<ApiResponse<null>>(`${RESOURCE}/${id}`).then((r) => r.data),
}
