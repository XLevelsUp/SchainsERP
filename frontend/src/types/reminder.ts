// "View Notes" / reminders — the Stock Management screen's per-head task list.
//
// Backend arrived in PR #50 (`c4dd0445`): `reminders` table,
// `App\Models\Reminder`, `ReminderController`, `apiResource('reminders')`.
// The schema matched the shape this file had guessed at almost exactly, but
// the serialisation did not — see the four notes below, all verified against
// the running API on 2026-10-08, not read off the controller.
//
// `ReminderWire` is what the endpoint actually sends. `Reminder` is what the
// rest of the app uses. `lib/remindersApi.ts` converts between them, so the
// oddities stay in one place instead of leaking into the panel.

/** A user as the API nests it — the FULL user_details row, ~60 fields. */
export interface ReminderWireUser {
  user_id: number
  name: string
  // Everything else on user_details rides along (including rak_cash_balance
  // and the running gram totals). password_hash is in the model's $hidden,
  // so nothing secret leaks. Not declared here because nothing reads it.
}

/**
 * ⚠️ `added_by` and `assign_to` change type depending on the endpoint.
 *
 * The model's relation methods are `addedBy()` / `assignTo()`, which Eloquent
 * snake-cases to `added_by` / `assign_to` — the same keys as the foreign-key
 * columns. `toArray()` merges relations AFTER attributes, so when a relation
 * is eager-loaded it overwrites the scalar:
 *
 *   index() / show()      -> { "added_by": { user_id: 2, name: "..." } }
 *   store() / update()    -> { "added_by": 2 }
 *
 * `index` and `show` load the relations; `store` and `update` return the bare
 * model. So the same field is an object or an integer depending on how you
 * got the row. normaliseReminder() flattens both into id + name.
 */
export type ReminderWireUserRef = number | ReminderWireUser | null

export interface ReminderWire {
  id: number
  description: string
  // Absent from the store() response — it is a database default, and the
  // model is returned before any refresh. Treated as false when missing.
  is_completed?: boolean
  added_by: ReminderWireUserRef
  assign_to: ReminderWireUserRef
  // A `date` column cast to `date`, so it serialises as a UTC instant of
  // midnight-in-Asia/Kolkata: "2026-10-14T18:30:00.000000Z" for the 15th.
  // Never slice this string — see toDateInputValue() in lib/date.ts.
  remainder_at: string | null
  is_viewed?: boolean
  // Laravel's standard timestamps(). NOT `added_at`, which is what the rest
  // of this backend uses and what this file previously assumed.
  created_at?: string | null
  updated_at?: string | null
}

/** The normalised row the panel works with. */
export interface Reminder {
  id: number
  description: string
  is_completed: boolean
  /** Nullable: the FK is `onDelete('set null')`. */
  added_by: number | null
  /** Null when the response carried only the scalar id (store/update). */
  added_by_name: string | null
  assign_to: number | null
  assign_to_name: string | null
  /** Always "YYYY-MM-DD" or null — the UTC shift already undone. */
  remainder_at: string | null
  is_viewed: boolean
  created_at: string | null
}

export interface ReminderCreate {
  description: string
  /** "YYYY-MM-DD". `nullable|date` server-side. */
  remainder_at: string
  /** `required|exists:user_details,user_id`. */
  assign_to: number
}

export type ReminderUpdate = Partial<ReminderCreate> & {
  is_completed?: boolean
  is_viewed?: boolean
}
