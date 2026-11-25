// How a cash transaction's `type` is shown (Cash Management's history
// tables and One Day Action's cash section).
//
// Coloured by cash direction, the same way Cash Management's quick-action
// buttons are: oxblood for types where cash leaves the head (the Pay
// group), verdigris where it reaches the head (the Receive group). The
// label tells the types apart; the colour says which way the money went.

export interface CashTypeBadge {
  label: string
  class: string
}

const OUT_BADGE = 'bg-outflow-50 text-outflow-700'
const IN_BADGE = 'bg-inflow-50 text-inflow-700'

const CASH_TYPE_BADGES: Record<string, CashTypeBadge> = {
  EXPENSE: { label: 'Pay', class: OUT_BADGE },
  INCOME: { label: 'Receive', class: IN_BADGE },
  AUTO_ENTRY: { label: 'Auto Entry', class: IN_BADGE },
  PURCHASE_GOLD: { label: 'Purchase Gold', class: OUT_BADGE },
  SALE_GOLD: { label: 'Sale Gold', class: IN_BADGE },
  CASH_TO_GOLD: { label: 'Cash To Gold', class: IN_BADGE },
  GOLD_TO_CASH: { label: 'Gold To Cash', class: OUT_BADGE },
  INTERNAL_TRANSFER: { label: 'Internal', class: 'bg-slate-100 text-slate-500' },
  OUT_CASH_CONVERTER: { label: 'Out Cash Converter', class: OUT_BADGE },
  IN_CASH_CONVERTER: { label: 'In Cash Converter', class: IN_BADGE },
}

/** A type the table doesn't know shows as itself, in neutral grey. */
export function cashTypeBadge(type: string): CashTypeBadge {
  return CASH_TYPE_BADGES[type] ?? { label: type, class: 'bg-slate-100 text-slate-600' }
}

/** "Cash on hand", or the bank's name for a bank (RTGS) entry. */
export function cashMethodLabel(paymentMethod: string | null | undefined, bankName?: string | null): string {
  if (paymentMethod === 'BANK') return bankName || 'Bank'
  return 'Cash on hand'
}
