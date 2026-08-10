import type { CashTxnSourceType } from '@/types'

/*
|--------------------------------------------------------------------------
| Shared enum option lists
|--------------------------------------------------------------------------
| Mirrors the `payment_method` enum validated by
| StoreCashTxnDetailRequest::rules() (in:CASH_ON_HAND,BANK). The rest of
| the old option lists here (transaction type, entry type, bill/payment
| type, arithmetic operation, cash loan type) were dropped along with the
| CRUD form that used them — see cashTxnDetailsApi.ts.
*/

export const sourceTypeOptions: { value: CashTxnSourceType; label: string }[] = [
  { value: 'CASH_ON_HAND', label: 'Cash on hand' },
  { value: 'BANK', label: 'Bank' },
]
