// "View Notes" / reminders panel — the Stock Management screen's per-head
// task list (legacy "Create new Remainder" dialog: Description, Remainder
// Date, Assign_to).
//
// ⚠️ THERE IS NO BACKEND FOR THIS FEATURE. Checked exhaustively (2026-09-25):
// no migration, no model, no controller, no route anywhere in schainbackend,
// and no mention in the API testing doc. The only "remainder" hits anywhere
// in the backend are CashTxnDetail's own `remainder`/`remainder_at` columns
// (a single reminder date on one cash transaction) and the unrelated
// `is_remainder_shown` per-user UI flag — neither is this feature.
//
// The shape below is reverse-engineered from the two legacy screenshots this
// was built from, not from any backend contract, because none exists yet.
// See PENDING_WORK.md backend ask for the migration/controller this assumes
// and needs. Every field name here is a proposal.
export interface Reminder {
  id: number
  description: string
  is_completed: boolean
  // Both are user references. The legacy screen shows a name ("NIHAA(PR)"),
  // so the API presumably returns the same nested-user shape other
  // resources here use (CustomerTouchUserMapping's `user`, for example)
  // rather than a bare id — modelled optimistically as such, with the
  // scalar id kept too since something has to survive a plain update()
  // response the way CustomerTouchUserMapping's does.
  added_by: number
  added_by_user?: { user_id: number; name: string } | null
  assign_to: number
  assign_to_user?: { user_id: number; name: string } | null
  remainder_at: string
  is_viewed: boolean
  added_at?: string
  updated_at?: string
}

export interface ReminderCreate {
  description: string
  remainder_at: string
  assign_to: number
}

export type ReminderUpdate = Partial<ReminderCreate> & {
  is_completed?: boolean
  is_viewed?: boolean
}
