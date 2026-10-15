function pad(n: number): string {
  return String(n).padStart(2, '0')
}

/** Formats a date/time value as "dd/mm/yy hh:mm". */
export function formatDateTime(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const dd = pad(date.getDate())
  const mm = pad(date.getMonth() + 1)
  const yy = pad(date.getFullYear() % 100)
  const hh = pad(date.getHours())
  const min = pad(date.getMinutes())
  return `${dd}/${mm}/${yy} ${hh}:${min}`
}

/** Today's date as "YYYY-MM-DD", for pre-filling `<input type="date">` values. */
export function todayDateInputValue(): string {
  const date = new Date()
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** Now, as "YYYY-MM-DDTHH:MM" — for pre-filling `<input type="datetime-local">`. */
export function nowDateTimeInputValue(): string {
  const date = new Date()
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Now, as "YYYY-MM-DD HH:MM:SS" — the stamp printed on report slips. */
export function nowTimestamp(): string {
  const date = new Date()
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    ` ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
  )
}

/** Converts a `datetime-local` input value ("YYYY-MM-DDTHH:MM") to the backend's "YYYY-MM-DD HH:MM:SS". */
export function toBackendDateTime(value: string): string {
  if (!value) return ''
  return `${value.replace('T', ' ')}:00`
}

/**
 * Normalises any backend date or date-time to "YYYY-MM-DD" for an
 * `<input type="date">`.
 *
 * Not the same as `value.slice(0, 10)`, and that difference matters. A
 * Laravel `date` cast serialises midnight **in the app timezone** as a UTC
 * instant, so `reminders.remainder_at` = 2026-10-15 comes back as
 * "2026-10-14T18:30:00.000000Z" (Asia/Kolkata is +05:30, per the backend's
 * config/app.php). Slicing that string yields the 14th — a day early. Reading
 * the instant back through local getters yields the 15th, which is the date
 * that was stored.
 *
 * Assumes the viewer's clock is on the same timezone as the server, which
 * holds for this ERP's operators. A browser set to UTC would still read a
 * day early; the real fix is the backend sending a bare "YYYY-MM-DD".
 */
export function toDateInputValue(value?: string | null): string {
  if (!value) return ''
  // Already a plain date — no instant to convert, no shift to undo.
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** Formats a date-only value (e.g. "YYYY-MM-DD") as "dd/mm/yy". */
export function formatDateOnly(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value.length <= 10 ? `${value}T00:00:00` : value)
  if (Number.isNaN(date.getTime())) return value
  const dd = pad(date.getDate())
  const mm = pad(date.getMonth() + 1)
  const yy = pad(date.getFullYear() % 100)
  return `${dd}/${mm}/${yy}`
}
