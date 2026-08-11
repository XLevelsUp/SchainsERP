import type { UserDetailListItem } from '@/types'

// Shared label formatter for every user picker in the app — matches the
// legacy app's rich option text (name, role, current gold position).
// GET /user-details (list) only returns gm/purity/type, not cash/rtgs
// balance, so gold position is the richest detail the list endpoint can
// support without an extra per-row fetch (see UserDetailListItem).
export function userOptionLabel(user: UserDetailListItem): string {
  const type = user.type ?? '—'
  return `${user.name} (${type}) — Gm ${user.gm} · Purity ${user.purity}`
}
