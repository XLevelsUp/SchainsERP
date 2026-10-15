import { api } from './api'
import { toDateInputValue } from './date'
import type {
  Reminder,
  ReminderCreate,
  ReminderUpdate,
  ReminderWire,
  ReminderWireUserRef,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Reminders — apiResource('reminders'), added in PR #50
|--------------------------------------------------------------------------
| Backs the View Notes panel. Was speculative until PR #50; now real, and
| the contract differs from what was guessed in three ways worth knowing
| before editing anything here. All three verified against the running API
| on 2026-10-08.
|
| 1. NO RESPONSE ENVELOPE. Every other endpoint in this app returns
|    { success, message, data }. ReminderController returns the bare model
|    or array. So these calls use api.get<T> directly with no `.data`
|    unwrap — do not "fix" that to match the other api modules.
|
| 2. added_by / assign_to ARE SOMETIMES OBJECTS. index/show eager-load the
|    relations, whose snake_cased names collide with the FK columns and
|    overwrite them; store/update return the bare integers. See
|    types/reminder.ts. normaliseUser() below accepts either.
|
| 3. DATES COME BACK A DAY EARLY if you slice them. `remainder_at` is a
|    date column serialised as a UTC instant of midnight in Asia/Kolkata.
|    toDateInputValue() converts it back; see its comment in lib/date.ts.
|
| DELETE returns 204 with an empty body, which api.ts already handles.
|--------------------------------------------------------------------------
*/

const RESOURCE = '/reminders'

function normaliseUserId(ref: ReminderWireUserRef): number | null {
  if (ref === null || ref === undefined) return null
  return typeof ref === 'number' ? ref : ref.user_id
}

function normaliseUserName(ref: ReminderWireUserRef): string | null {
  return typeof ref === 'object' && ref !== null ? ref.name : null
}

function normaliseReminder(wire: ReminderWire): Reminder {
  return {
    id: wire.id,
    description: wire.description,
    // Absent on the store() response — a missing flag means not yet set.
    is_completed: wire.is_completed ?? false,
    added_by: normaliseUserId(wire.added_by),
    added_by_name: normaliseUserName(wire.added_by),
    assign_to: normaliseUserId(wire.assign_to),
    assign_to_name: normaliseUserName(wire.assign_to),
    remainder_at: wire.remainder_at ? toDateInputValue(wire.remainder_at) : null,
    is_viewed: wire.is_viewed ?? false,
    created_at: wire.created_at ?? null,
  }
}

export const remindersApi = {
  // GET /reminders — returns every reminder, unscoped and unpaginated
  // (ReminderController::index is a bare ->get()). Flagged to the backend;
  // the panel filters client-side in the meantime.
  list: () => api.get<ReminderWire[]>(RESOURCE).then((rows) => rows.map(normaliseReminder)),

  // POST /reminders — added_by is set server-side from the token, never sent.
  // The response carries no relations, so the returned row has ids but no
  // names; the panel fills those from its own user list.
  create: (payload: ReminderCreate) =>
    api.post<ReminderWire>(RESOURCE, payload).then(normaliseReminder),

  update: (id: number, payload: ReminderUpdate) =>
    api.put<ReminderWire>(`${RESOURCE}/${id}`, payload).then(normaliseReminder),

  remove: (id: number) => api.delete<null>(`${RESOURCE}/${id}`),
}
