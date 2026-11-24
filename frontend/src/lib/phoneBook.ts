import type { PhoneBookContact } from '@/types'

// Helpers for the admin console's Phone Book page (2026-11-24).

/**
 * A WhatsApp chat link for a phone book number, or null when the number
 * can't be one. Numbers are Indian 10-digit mobiles, so 91 is added; a
 * number already carrying 91 (12 digits) is used as it is.
 */
export function whatsappUrl(phone: string | null | undefined): string | null {
  const digits = (phone ?? '').replace(/\D/g, '')
  if (digits.length === 10) return `https://wa.me/91${digits}`
  if (digits.length === 12 && digits.startsWith('91')) return `https://wa.me/${digits}`
  return null
}

/**
 * The numbers of the selected contacts, one per line, for pasting into a
 * WhatsApp broadcast list (the toolbar's WhatsApp button). Blanks and
 * repeats are left out.
 */
export function numbersText(contacts: Pick<PhoneBookContact, 'phone_no'>[]): string {
  const seen = new Set<string>()
  for (const { phone_no } of contacts) {
    const phone = (phone_no ?? '').trim()
    if (phone) seen.add(phone)
  }
  return [...seen].join('\n')
}

function csvCell(value: string): string {
  return /[",\n\r]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value
}

/** The export: the listed contacts as CSV, in the order shown. */
export function phoneBookCsv(contacts: PhoneBookContact[]): string {
  const header = ['#', 'Name', 'Phone No', 'Role', 'Is Active']
  const rows = contacts.map((c, index) => [
    String(index + 1),
    c.name,
    // Keeps a leading 0 when the file is opened in Excel.
    c.phone_no ? `="${c.phone_no}"` : '',
    c.role?.role_name ?? '',
    c.is_active ? 'Yes' : 'No',
  ])
  return [header, ...rows].map((row) => row.map(csvCell).join(',')).join('\r\n')
}
