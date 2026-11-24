import { describe, expect, it } from 'vitest'
import { addDecimal, isValidOpeningValue, pendingFor, unitOf } from '@/lib/openingBalance'
import type { OpeningBalanceInput } from '@/types'

const entry = (over: Partial<OpeningBalanceInput>): OpeningBalanceInput => ({
  field: 'GRAMS',
  item_id: null,
  value: '1',
  remarks: '',
  ...over,
})

describe('opening balance helpers', () => {
  it('adds exactly, at 4 decimals, negatives included', () => {
    expect(addDecimal('0.1', '0.2')).toBe('0.3000')
    expect(addDecimal('10.5', '-2.25')).toBe('8.2500')
    expect(addDecimal('0', '-0.5')).toBe('-0.5000')
    expect(addDecimal(650.5, '0.0001')).toBe('650.5001')
    expect(addDecimal(null, 'junk')).toBe('0.0000')
  })

  it('accepts a non-zero value with up to 4 decimals', () => {
    for (const ok of ['10', '-2', '0.5', '-0.0001', '123456789012.1234']) expect(isValidOpeningValue(ok)).toBe(true)
    for (const bad of ['0', '-0.00', '', '1.23456', 'abc', '1,000', '1234567890123']) expect(isValidOpeningValue(bad)).toBe(false)
  })

  it('sums what is waiting for a figure; a grand total includes its items', () => {
    const pending = [
      entry({ field: 'ITEM_GRAMS', item_id: 9, value: '10' }),
      entry({ field: 'ITEM_GRAMS', item_id: 1, value: '2.5' }),
      entry({ field: 'ITEM_PURITY', item_id: 9, value: '9.2' }),
      entry({ field: 'GRAMS', value: '-1' }),
      entry({ field: 'CASH', value: '2500' }),
    ]
    expect(pendingFor(pending, 'ITEM_GRAMS', 9)).toBe('10.0000')
    expect(pendingFor(pending, 'ITEM_GRAMS', 1)).toBe('2.5000')
    expect(pendingFor(pending, 'GRAMS')).toBe('11.5000')
    expect(pendingFor(pending, 'PURITY')).toBe('9.2000')
    expect(pendingFor(pending, 'CASH')).toBe('2500.0000')
    expect(pendingFor(pending, 'RTGS')).toBe('0.0000')
    expect(unitOf('CASH')).toBe('₹')
    expect(unitOf('ITEM_PURITY')).toBe('g')
  })
})
