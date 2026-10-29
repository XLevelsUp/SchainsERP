// Display helpers shared by the System Logs table and its detail modal.

// Named to match the screens the entries come from. Anything not listed
// (a new action the backend starts logging) falls back to a humanised code.
const ACTION_LABELS: Record<string, string> = {
  STOCK_IN: 'Stock In',
  STOCK_OUT: 'Stock Out',
  GMS_IN: 'GMS In',
  GMS_OUT: 'GMS Out',
  NUMERIC_WASTE_IN: 'Numeric Wastage In',
  NUMERIC_WASTE_OUT: 'Numeric Wastage Out',
  ITEM_CHANGE: 'Item Change',
  ITEM_CONVERSION: 'Item Conversion',
  STOCK_AUTO_ENTRY: 'Stock Auto Entry',
  STOCK_HIDE: 'Stock Hide',
  STOCK_UNHIDE: 'Stock Unhide',
  STOCK_CASH_OUT: 'Stock Cash Out',
  FITEM_IN: 'Fitem In',
  FITEM_OUT: 'Fitem Out',
  CASH_IN: 'Cash In',
  CASH_OUT: 'Cash Out',
  CASH_AUTO_ENTRY: 'Cash Auto Entry',
  PURCHASE_GOLD: 'Purchase Gold',
  SALE_GOLD: 'Sale Gold',
  CASH_TO_GOLD: 'Cash To Gold',
  GOLD_TO_CASH: 'Gold To Cash',
}

function humanize(code: string): string {
  return code
    .toLowerCase()
    .split('_')
    .filter(Boolean)
    .map((word) => word[0].toUpperCase() + word.slice(1))
    .join(' ')
}

export function systemLogActionLabel(action: string): string {
  return ACTION_LABELS[action] ?? humanize(action)
}

export function systemLogModuleLabel(module: string): string {
  return humanize(module)
}

// Same STOCK/CASH palette One Day Action uses for its Source column.
export function systemLogModuleClass(module: string): string {
  if (module === 'CASH') return 'bg-amber-50 text-amber-700'
  return 'bg-slate-100 text-slate-600'
}

// Metadata keys whose value is a user_details id, resolved to a name in the
// detail view.
export const SYSTEM_LOG_PARTY_KEYS = new Set([
  'given_by',
  'given_to',
  'sender_id',
  'recipient_id',
  'user_id',
  'customer_id',
])

const METADATA_LABELS: Record<string, string> = {
  given_by: 'Given by',
  given_to: 'Given to',
  sender_id: 'Sender',
  recipient_id: 'Recipient',
  user_id: 'User',
  customer_id: 'Customer',
  items_count: 'Items',
  total_transactions: 'Transactions',
  total_cash: 'Total cash',
  total_grams: 'Total grams (g)',
  touch: 'Touch',
  amount: 'Amount',
  payment_method: 'Payment method',
  stock_ids: 'Stock IDs',
  type: 'Type',
}

export function systemLogMetadataLabel(key: string): string {
  return METADATA_LABELS[key] ?? humanize(key)
}

export type SystemLogNameLookup = (id: number) => string | undefined

// "Name (#12)" when the id resolves, "#12" otherwise.
export function systemLogPartyLabel(id: number | null | undefined, nameOf: SystemLogNameLookup) {
  if (id === null || id === undefined) return '—'
  const name = nameOf(id)
  return name ? `${name} (#${id})` : `#${id}`
}

// Log descriptions name parties as "User #12" (the backend stores no
// names); swap in the name wherever it is known, keeping the id.
export function systemLogDescription(text: string | null, nameOf: SystemLogNameLookup): string {
  if (!text) return '—'
  return text.replace(/User #(\d+)/g, (match, id: string) => {
    const name = nameOf(Number(id))
    return name ? `${name} (#${id})` : match
  })
}
