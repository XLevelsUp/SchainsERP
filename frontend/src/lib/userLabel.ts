import type { UserDetailListItem } from '@/types'

// Shared label formatter for every user picker in the app — matches the
// legacy app's rich option text (name, role, current position). Which
// position shows depends on which module the row was fetched with
// (userDetailsApi.list(type, module)): hand_cash/rtgs_cash for 'cash'
// pickers, gm/purity for 'stock' pickers (or callers that didn't pass a
// module at all, kept as the fallback for backward compatibility).
export function userOptionLabel(user: UserDetailListItem): string {
  const type = user.type ?? '—'
  if (user.hand_cash !== undefined && user.rtgs_cash !== undefined) {
    return `${user.name} (${type}) — Hand Cash ${user.hand_cash} · RTGS Cash ${user.rtgs_cash}`
  }
  return `${user.name} (${type}) — Gm ${user.gm ?? '0.000'} · Purity ${user.purity ?? '0.000'}`
}
