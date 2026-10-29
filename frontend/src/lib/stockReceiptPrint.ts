import { nextTick, ref } from 'vue'
import { stockReportsApi } from './stockReportsApi'
import { useToastStore } from '@/stores/toast'

/*
| Stock transaction receipt — the legacy "X TO Y" slip, printed straight to
| the browser's print dialog (no in-app preview).
|
| The backend has no stock print endpoint (the only one is the cash
| /cash-txn-details/print-report), so the slip is assembled from the two
| endpoints that hold its data, nothing else:
|   1. GET /stock/reports/id-wise?stock_id= — the one endpoint keyed by a
|      single stock id: date/time, from/to names, item + item remarks. The
|      row is always in `transactions` (the lot row plus its drawdowns).
|   2. GET /stock/reports/items-obcb narrowed to that exact second
|      (added_at is timestamp(0), so from = to = added_at matches only rows
|      saved in that second) and to the viewed-as user — the only endpoint
|      that returns OB/CB. That user's side of the row's obcb snapshot gives
|      the "<name> Bal OB / CB" lines. Its grams/touch/purity are preferred
|      when present: for auto entries they are the receiving side's values,
|      which is what moves that OB to that CB.
|
| Pcs is in neither (BACKEND_TODO.md §15), so callers that have the history
| row pass it in. If the row doesn't involve the viewed-as user (a head
| looking at a mapped employee's entry), items-obcb doesn't return it and
| OB/CB print as "—".
*/

export interface StockReceipt {
  givenBy: string
  givenTo: string
  // "YYYY-MM-DD HH:MM:SS"
  addedAt: string
  item: string
  pcs: number | null
  grams: number
  touch: number
  purity: number
  balanceName: string
  ob: number | null
  cb: number | null
}

export interface StockReceiptViewer {
  // Whose ledger the receipt is printed from: the picked user, else the
  // signed-in head.
  userId: number
  name: string
}

// Shared with StockReceiptSlip's @media print rule.
export const STOCK_RECEIPT_PRINT_CLASS = 'printing-stock-receipt'

async function loadReceipt(
  stockId: number,
  viewer: StockReceiptViewer,
  pcs: number | null,
): Promise<StockReceipt> {
  const lot = await stockReportsApi.getIdWise(stockId)
  const txn = lot.transactions.find((row) => row.id === stockId)
  if (!txn) throw new Error(`Transaction #${stockId} was not returned by the stock report.`)

  const [date, time] = txn.added_at.split(' ')
  const ledger =
    date && time
      ? await stockReportsApi.getItemsObcb({
          employee_id: `user_${viewer.userId}`,
          from_date: date,
          from_time: time,
          to_date: date,
          to_time: time,
          page_size: 100,
        })
      : null
  const balance = ledger?.records.find((row) => row.stock_id === stockId) ?? null

  return {
    givenBy: txn.given_by,
    givenTo: txn.given_to,
    addedAt: txn.added_at,
    item: txn.item_remarks ? `${txn.item_name} (${txn.item_remarks})` : txn.item_name,
    pcs,
    grams: balance?.grams ?? txn.grams,
    touch: balance?.touch ?? txn.touch,
    purity: balance?.purity ?? txn.purity,
    balanceName: viewer.name,
    ob: balance?.ob_purity ?? null,
    cb: balance?.cb_purity ?? null,
  }
}

export function useStockReceiptPrint() {
  const toast = useToastStore()
  const receipt = ref<StockReceipt | null>(null)
  // The row being fetched — its button shows busy and every print button
  // is held off until the dialog opens, so a double click can't race.
  const printingId = ref<number | null>(null)

  async function printReceipt(stockId: number, viewer: StockReceiptViewer, pcs: number | null = null) {
    if (printingId.value !== null) return
    printingId.value = stockId
    try {
      receipt.value = await loadReceipt(stockId, viewer, pcs)
      document.body.classList.add(STOCK_RECEIPT_PRINT_CLASS)
      await nextTick()
      // afterprint rather than a synchronous cleanup: window.print() blocks
      // until the dialog closes in Chrome but returns immediately in Safari.
      window.addEventListener(
        'afterprint',
        () => document.body.classList.remove(STOCK_RECEIPT_PRINT_CLASS),
        { once: true },
      )
      window.print()
    } catch (err) {
      toast.show(err instanceof Error ? err.message : 'Could not load this transaction to print.', 'error')
    } finally {
      printingId.value = null
    }
  }

  return { receipt, printingId, printReceipt }
}
