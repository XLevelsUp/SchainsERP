import { describe, expect, it } from 'vitest'
import {
  ONE_DAY_ACTION_PAGE_SIZE,
  oneDayActionCsv,
  oneDayActionQuery,
  type OneDayActionFilterValues,
} from '@/lib/oneDayAction'
import { cashMethodLabel, cashTypeBadge } from '@/lib/cashTxnTypes'
import type { OneDayActionCashRow, OneDayActionStockRow } from '@/types'

const filters = (over: Partial<OneDayActionFilterValues> = {}): OneDayActionFilterValues => ({
  head_id: null,
  employee_id: null,
  item_id: null,
  type: null,
  given_by: null,
  given_to: null,
  role_id: null,
  from_date: '2026-11-25',
  to_date: '',
  ...over,
})

const stockRow: OneDayActionStockRow = {
  id: 7,
  entry_type: 'NORMAL',
  stock_type: 'OUT',
  given_by: 'HEAD A',
  given_to: 'EMP "A"',
  item_name: 'GOLD',
  grams: '10.5',
  touch: '92',
  purity: '9.66',
  waste_total: null,
  waste_value: null,
  remarks: 'zz',
  added_by: 3,
  added_at: '2026-11-25 10:00:00',
  is_hided: 0,
}

const cashRow: OneDayActionCashRow = {
  id: 9,
  type: 'INCOME',
  flow: 'IN',
  given_by: 'CUST',
  given_to: 'HEAD A',
  category_name: 'SALES',
  amount: '1000.00',
  payment_method: 'BANK',
  bank_name: 'SBI',
  remarks: null,
  added_by: 3,
  added_at: '2026-11-25 11:00:00',
}

describe('oneDayActionQuery', () => {
  it('sends only the filters that are set, from today, one day', () => {
    expect(oneDayActionQuery(filters(), { withHead: false })).toEqual({
      from_date: '2026-11-25',
      to_date: undefined,
      employee_id: undefined,
      item_id: undefined,
      type: undefined,
      given_by: undefined,
      given_to: undefined,
      role_id: undefined,
      section: undefined,
      page_no: 1,
      page_size: ONE_DAY_ACTION_PAGE_SIZE,
    })
  })

  it('maps every legacy filter', () => {
    const q = oneDayActionQuery(
      filters({ employee_id: 1, item_id: 2, type: 'IN', given_by: 3, given_to: 4, role_id: 5, to_date: '2026-11-30' }),
      { withHead: false, section: 'cash', pageNo: 3 },
    )
    expect(q).toMatchObject({
      employee_id: 1, item_id: 2, type: 'IN', given_by: 3, given_to: 4, role_id: 5,
      to_date: '2026-11-30', section: 'cash', page_no: 3,
    })
  })

  it('sends the head in the console only, and only when one is picked', () => {
    expect(oneDayActionQuery(filters({ head_id: 8 }), { withHead: true }).head_id).toBe(8)
    expect(oneDayActionQuery(filters({ head_id: null }), { withHead: true })).not.toHaveProperty('head_id')
    expect(oneDayActionQuery(filters({ head_id: 8 }), { withHead: false })).not.toHaveProperty('head_id')
  })
})

describe('oneDayActionCsv', () => {
  it('puts both sections in one file with a Section column', () => {
    const lines = oneDayActionCsv([stockRow], [cashRow]).split('\n')
    expect(lines).toHaveLength(3)
    expect(lines[0]).toMatch(/^"Section","ID","Date","Flow","Type"/)
    expect(lines[1]).toContain('"STOCK","7","2026-11-25 10:00:00","OUT","NORMAL","HEAD A","EMP ""A""","GOLD"')
    expect(lines[1]).toContain('"10.5","92","9.66"')
    expect(lines[2]).toContain('"CASH","9","2026-11-25 11:00:00","IN","Receive","CUST","HEAD A","","SALES"')
    expect(lines[2]).toContain('"1000.00","SBI"')
  })

  it('is just the header when nothing matched', () => {
    expect(oneDayActionCsv([], []).split('\n')).toHaveLength(1)
  })
})

describe('cash type labels', () => {
  it('labels and colours the types by direction', () => {
    expect(cashTypeBadge('EXPENSE')).toEqual({ label: 'Pay', class: 'bg-outflow-50 text-outflow-700' })
    expect(cashTypeBadge('SALE_GOLD').label).toBe('Sale Gold')
    expect(cashTypeBadge('SOMETHING_NEW')).toEqual({ label: 'SOMETHING_NEW', class: 'bg-slate-100 text-slate-600' })
  })

  it('names the bank for bank entries', () => {
    expect(cashMethodLabel('BANK', 'SBI')).toBe('SBI')
    expect(cashMethodLabel('BANK', null)).toBe('Bank')
    expect(cashMethodLabel('CASH_ON_HAND')).toBe('Cash on hand')
  })
})
