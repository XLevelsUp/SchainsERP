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
