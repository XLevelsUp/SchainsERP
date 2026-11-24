import type { OpeningBalanceField, OpeningBalanceInput } from '@/types'

// Helpers for the user form's opening balance modals (2026-11-24). Figures
// are worked out in exact decimals (scale 4, like bcmath on the server),
// never floats: 0.1 + 0.2 must show 0.3.

const SCALE = 4
const FACTOR = 10n ** BigInt(SCALE)

/** A value the modal accepts: up to 12 digits, 4 decimals, negative allowed, not 0. */
export function isValidOpeningValue(value: string): boolean {
  const text = value.trim()
  return /^-?\d{1,12}(\.\d{1,4})?$/.test(text) && !/^-?0+(\.0+)?$/.test(text)
}

function toUnits(value: string | number | null | undefined): bigint {
  let text = typeof value === 'number' ? value.toFixed(SCALE) : String(value ?? '').trim()
  if (!/^-?\d+(\.\d+)?$/.test(text)) text = '0'
  const negative = text.startsWith('-')
  const [whole = '0', fraction = ''] = text.replace('-', '').split('.')
  const units = BigInt(whole) * FACTOR + BigInt((fraction + '0000').slice(0, SCALE))
  return negative ? -units : units
}

function fromUnits(units: bigint): string {
  const negative = units < 0n
  const abs = negative ? -units : units
  const fraction = String(abs % FACTOR).padStart(SCALE, '0')
  return `${negative ? '-' : ''}${abs / FACTOR}.${fraction}`
}

/** a + b, exactly, as a scale-4 decimal string. */
export function addDecimal(a: string | number | null | undefined, b: string | number | null | undefined): string {
  return fromUnits(toUnits(a) + toUnits(b))
}

/** The grand total an item field moves along with it. */
export const GRAND_FIELD_OF: Partial<Record<OpeningBalanceField, OpeningBalanceField>> = {
  ITEM_GRAMS: 'GRAMS',
  ITEM_PURITY: 'PURITY',
}

/**
 * What a new user's pending opening balances add to one figure. A grand
 * total also gets its items' pending updates (an item update moves it).
 */
export function pendingFor(
  entries: OpeningBalanceInput[],
  field: OpeningBalanceField,
  itemId: number | null = null,
): string {
  return entries
    .filter((e) => {
      if (e.field === field) return GRAND_FIELD_OF[field] ? e.item_id === itemId : true
      return GRAND_FIELD_OF[e.field] === field
    })
    .reduce((sum, e) => addDecimal(sum, e.value), '0.0000')
}

export const OPENING_FIELD_LABELS: Record<OpeningBalanceField, string> = {
  ITEM_GRAMS: 'Grams',
  ITEM_PURITY: 'Purity',
  GRAMS: 'Grams grand total',
  PURITY: 'Purity grand total',
  CASH: 'Cash balance',
  RTGS: 'RTGS balance',
}

/** Unit shown beside the figures. */
export function unitOf(field: OpeningBalanceField): 'g' | '₹' {
  return field === 'CASH' || field === 'RTGS' ? '₹' : 'g'
}
