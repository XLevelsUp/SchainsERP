import { describe, expect, it } from 'vitest'
import { filterItems, itemsCsv, nextOrderNo, sortByOrder } from '@/lib/items'
import type { Item } from '@/types'

const item = (over: Partial<Item>): Item => ({
  item_id: 1,
  item_name: 'METAL',
  is_active: true,
  default_touch: 92,
  item_touch: 0,
  added_at: '2026-11-24 10:00:00',
  is_need_fitem_shown: false,
  mtouch: null,
  is_barcode: false,
  is_no_barcode: false,
  order_no: 1,
  ...over,
})

const legacy = [
  item({ item_id: 6, item_name: 'STONE', order_no: 6 }),
  item({ item_id: 1, item_name: 'METAL', order_no: 1 }),
  item({ item_id: 4, item_name: 'FITEM(75%)', order_no: 4, item_touch: 75.1, is_need_fitem_shown: true }),
  item({ item_id: 9, item_name: 'GOLD', order_no: 2 }),
  item({ item_id: 3, item_name: 'FITEM', order_no: 3, item_touch: 91.7, is_need_fitem_shown: true }),
  item({ item_id: 12, item_name: 'Ring', order_no: 12, is_active: false }),
]

describe('the Items page helpers', () => {
  it('orders items by order number, as every dropdown shows them', () => {
    expect(sortByOrder(legacy).map((i) => i.item_name)).toEqual(['METAL', 'GOLD', 'FITEM', 'FITEM(75%)', 'STONE', 'Ring'])
  })

  it('filters like the legacy columns', () => {
    const base = { name: '', fitem: 'all' as const, active: 'all' as const, orderNo: '' }
    expect(filterItems(legacy, { ...base, name: 'fitem' }).map((i) => i.item_name)).toEqual(['FITEM(75%)', 'FITEM'])
    expect(filterItems(legacy, { ...base, fitem: 'yes' })).toHaveLength(2)
    expect(filterItems(legacy, { ...base, active: 'no' }).map((i) => i.item_name)).toEqual(['Ring'])
    expect(filterItems(legacy, { ...base, orderNo: '1' }).map((i) => i.item_name)).toEqual(['METAL', 'Ring'])
  })

  it('suggests the next free order number', () => {
    expect(nextOrderNo(legacy)).toBe(13)
    expect(nextOrderNo([])).toBe(1)
  })

  it('exports the legacy columns in the order shown', () => {
    const csv = itemsCsv(sortByOrder(legacy).slice(0, 4))
    expect(csv.split('\r\n')).toEqual([
      'Orders No,Item Name,Item Touch,Is Need Fitem Shown,Is Active',
      '1,METAL,0,No,Yes',
      '2,GOLD,0,No,Yes',
      '3,FITEM,91.7,Yes,Yes',
      '4,FITEM(75%),75.1,Yes,Yes',
    ])
  })
})
