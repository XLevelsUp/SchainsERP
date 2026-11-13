import type { CashToGoldAmountSourceInput, GoldEntryInput } from '@/types'

// Helpers for opening a gold modal in "correct" mode (BACKEND_TODO §17
// phase 3): turning the backend's decimal strings back into the numbers the
// forms work with.

export function toNumber(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null
  const n = Number(value)
  return Number.isFinite(n) ? n : null
}

// Payment rows as the modals hold them; at least one row so the section
// renders like a new entry.
export function toSourceInputs(input: GoldEntryInput): CashToGoldAmountSourceInput[] {
  const rows = input.amount_sources.map((s) => ({
    source: s.source,
    bank_id: s.bank_id,
    amount: toNumber(s.amount),
  }))
  return rows.length > 0 ? rows : [{ source: 'CASH_ON_HAND', bank_id: null, amount: null }]
}

export const CORRECTION_NOTE =
  'Saving reverses the original entry and posts this corrected one, both dated today. The customer, head and item stay as they were.'
