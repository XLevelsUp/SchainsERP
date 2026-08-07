import type {
  ArithmeticOperation,
  BillPaymentCashType,
  CashLoanType,
  CashTxnEntryType,
  CashTxnSourceType,
  CashTxnType,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Shared enum option lists
|--------------------------------------------------------------------------
| Mirrors the exact `in:` enum lists validated by
| CashTxnDetailController::storeValidator()/updateValidator() — nothing
| here is invented, only humanized for display. Shared between the full
| transaction form and the New Transaction wizard so the two never drift.
*/

export const typeOptions: { value: CashTxnType; label: string }[] = [
  { value: 'INCOME', label: 'Income' },
  { value: 'EXPENSE', label: 'Expense' },
  { value: 'AUTO_ENTRY', label: 'Auto Entry' },
  { value: 'CASH_TO_GOLD', label: 'Cash to Gold' },
  { value: 'PURCHASE_GOLD', label: 'Purchase Gold' },
  { value: 'SALE_GOLD', label: 'Sale Gold' },
  { value: 'AMOUNT_TRANSFER', label: 'Amount Transfer' },
  { value: 'GOLD_TO_CASH', label: 'Gold to Cash' },
  { value: 'INTERNAL_TRANSFER', label: 'Internal Transfer' },
  { value: 'IN_CASH_CONVERTER', label: 'In Cash Converter' },
  { value: 'OUT_CASH_CONVERTER', label: 'Out Cash Converter' },
  { value: 'CashToGold', label: 'Cash to Gold (legacy)' },
  { value: 'CASH_LOAN', label: 'Cash Loan' },
]

export function typeLabel(value: CashTxnType) {
  return typeOptions.find((o) => o.value === value)?.label ?? value
}

// Only these two types actually drive balance math server-side — every
// other type stores the entry with closing balances equal to whatever
// opening values are submitted (CashTxnDetailController::calculateBalances).
export const AUTO_CALC_TYPES: CashTxnType[] = ['INCOME', 'EXPENSE']

// Groups the wizard's "In" / "Out" / "Other" step around, matching the
// legacy dashboard's IN tab group (IN, AUTO_ENTRY, CashToGold, SaleGold) and
// OUT tab group (OUT, PurchaseGold, GoldToCash) one-for-one. Types outside
// either legacy tab group fall into "Other" so nothing becomes unreachable.
export const IN_TYPES: CashTxnType[] = ['INCOME', 'AUTO_ENTRY', 'CASH_TO_GOLD', 'SALE_GOLD']
export const OUT_TYPES: CashTxnType[] = ['EXPENSE', 'PURCHASE_GOLD', 'GOLD_TO_CASH']
export const OTHER_TYPES: CashTxnType[] = typeOptions
  .map((o) => o.value)
  .filter((v) => !IN_TYPES.includes(v) && !OUT_TYPES.includes(v))

export const sourceTypeOptions: { value: CashTxnSourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]

export const txnTypeOptions: { value: CashTxnEntryType; label: string }[] = [
  { value: 'NORMAL', label: 'Normal' },
  { value: 'ATTENDANCE', label: 'Attendance' },
]

export const billPaymentCashTypeOptions: { value: BillPaymentCashType; label: string }[] = [
  { value: 'ON_SPOT_NILL', label: 'On spot / nil' },
  { value: 'ON_ACCOUNTABLE', label: 'On accountable' },
  { value: 'PARTIAL_PAYMENT', label: 'Partial payment' },
]

export const arithmeticOperationOptions: { value: ArithmeticOperation; label: string }[] = [
  { value: '+', label: '+ (add)' },
  { value: '-', label: '- (subtract)' },
]

export const cashLoanTypeOptions: { value: CashLoanType; label: string }[] = [
  { value: 'cash_loan_taken', label: 'Cash loan taken' },
  { value: 'cash_loan_given', label: 'Cash loan given' },
  { value: 'interest_payment', label: 'Interest payment' },
  { value: 'interest_receipt', label: 'Interest receipt' },
]
