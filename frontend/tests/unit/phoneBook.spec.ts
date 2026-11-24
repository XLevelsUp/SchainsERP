import { describe, expect, it } from 'vitest'
import { numbersText, phoneBookCsv, whatsappUrl } from '@/lib/phoneBook'
import type { PhoneBookContact } from '@/types'

const contact = (over: Partial<PhoneBookContact>): PhoneBookContact => ({
  id: 1,
  name: 'PANNEER',
  phone_no: '9942172556',
  role_id: 1,
  is_active: true,
  user_id: null,
  profile_image_url: null,
  role: { id: 1, role_name: 'CUSTOMER' },
  ...over,
})

describe('the phone book helpers', () => {
  it('builds a WhatsApp chat link for a 10-digit number only', () => {
    expect(whatsappUrl('9942172556')).toBe('https://wa.me/919942172556')
    expect(whatsappUrl('919942172556')).toBe('https://wa.me/919942172556')
    expect(whatsappUrl('99421')).toBeNull()
    expect(whatsappUrl('')).toBeNull()
    expect(whatsappUrl(null)).toBeNull()
  })

  it('copies the ticked numbers one per line, without repeats or blanks', () => {
    expect(
      numbersText([{ phone_no: '9942172556' }, { phone_no: ' 8778480227 ' }, { phone_no: '9942172556' }, { phone_no: '' }]),
    ).toBe('9942172556\n8778480227')
    expect(numbersText([])).toBe('')
  })

  it('exports CSV in the order shown, keeping a leading 0 and quoting commas', () => {
    const csv = phoneBookCsv([
      contact({}),
      contact({ id: 2, name: 'RAJU, BALLS', phone_no: '0894620524', is_active: false, role: { id: 2, role_name: 'EMPLOYEE' } }),
    ])
    expect(csv.split('\r\n')).toEqual([
      '#,Name,Phone No,Role,Is Active',
      '1,PANNEER,"=""9942172556""",CUSTOMER,Yes',
      '2,"RAJU, BALLS","=""0894620524""",EMPLOYEE,No',
    ])
  })
})
