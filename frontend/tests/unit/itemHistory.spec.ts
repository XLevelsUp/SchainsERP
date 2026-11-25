import { describe, expect, it } from 'vitest'
import {
  ITEM_CHANGE_PAGE_SIZE,
  alloySummary,
  defaultItemHistoryFilters,
  formatWeight,
  itemHistoryQuery,
  type ItemHistoryFilterValues,
} from '@/lib/itemHistory'

// Item Change History / Item Conversion Group History (2026-11-25).

const filters = (over: Partial<ItemHistoryFilterValues> = {}): ItemHistoryFilterValues => ({
  head_id: null,
  item_id: null,
  from_date: '2026-11-25',
  to_date: '',
  ...over,
})

describe('defaultItemHistoryFilters', () => {
  it('opens on the current month, the 1st to today', () => {
    expect(defaultItemHistoryFilters(new Date(2026, 10, 25))).toEqual({
      head_id: null,
      item_id: null,
      from_date: '2026-11-01',
      to_date: '2026-11-25',
    })
    expect(defaultItemHistoryFilters(new Date(2026, 0, 5))).toMatchObject({ from_date: '2026-01-01', to_date: '2026-01-05' })
  })
})

describe('itemHistoryQuery', () => {
  it('sends the dates, the item and the page; an empty To Date means one day', () => {
    expect(itemHistoryQuery(filters({ item_id: 9 }), { withHead: false, pageSize: ITEM_CHANGE_PAGE_SIZE })).toEqual({
      from_date: '2026-11-25',
      to_date: undefined,
      item_id: 9,
      page_no: 1,
      page_size: ITEM_CHANGE_PAGE_SIZE,
    })
    expect(
      itemHistoryQuery(filters({ from_date: '', to_date: '2026-11-30' }), { withHead: false, pageNo: 3, pageSize: 25 }),
    ).toMatchObject({ from_date: undefined, to_date: '2026-11-30', item_id: undefined, page_no: 3, page_size: 25 })
  })

  it('sends head_id only in the console, and only when a head is picked', () => {
    expect(itemHistoryQuery(filters({ head_id: 4 }), { withHead: true, pageSize: 25 }).head_id).toBe(4)
    expect(itemHistoryQuery(filters({ head_id: 4 }), { withHead: false, pageSize: 25 })).not.toHaveProperty('head_id')
    expect(itemHistoryQuery(filters(), { withHead: true, pageSize: 25 })).not.toHaveProperty('head_id')
  })
})

describe('formatting', () => {
  it('shows weights with up to 4 places and a dash for none', () => {
    expect(formatWeight('12.0000')).toBe('12')
    expect(formatWeight('9.9900')).toBe((9.99).toLocaleString())
    expect(formatWeight(null)).toBe('—')
    expect(formatWeight('')).toBe('—')
    expect(formatWeight('abc')).toBe('—')
  })

  it('summarises the alloys of a conversion', () => {
    expect(alloySummary({ alloys: [] })).toBe('—')
    expect(
      alloySummary({
        alloys: [
          { alloy_item_id: 4, alloy_item_name: 'Copper', alloy_percentage: '10.0000', alloy_grams: '1.2000' },
          { alloy_item_id: 5, alloy_item_name: null, alloy_percentage: '5.0000', alloy_grams: '0.6000' },
        ],
      }),
    ).toBe(`Copper 10% · ${(1.2).toLocaleString()} g, Item #5 5% · ${(0.6).toLocaleString()} g`)
  })
})
