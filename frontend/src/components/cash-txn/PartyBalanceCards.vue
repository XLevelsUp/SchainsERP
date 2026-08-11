<script setup lang="ts">
// OB (opening balance, fetched live) / CB (closing balance, calculated
// client-side from the form's amount + payment method) for both parties —
// matches the legacy "Add Expense" dialog's balance tables. CB is null
// until there's enough on the form to compute it (no amount entered yet);
// shown as "—" rather than the legacy dialog's broken "NaN".
defineProps<{
  leftLabel: string
  leftObCash: number
  leftObRtgs: number
  leftCbCash: number | null
  leftCbRtgs: number | null
  rightLabel: string
  rightObCash: number
  rightObRtgs: number
  rightCbCash: number | null
  rightCbRtgs: number | null
  isLoading?: boolean
}>()

function fmt(value: number | null) {
  return value === null ? '—' : value.toLocaleString()
}
</script>

<template>
  <div v-if="isLoading" class="text-sm text-slate-500">Loading balances…</div>
  <div v-else class="grid gap-4 sm:grid-cols-2">
    <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
          <th class="px-3 py-2">{{ leftLabel }}</th>
          <th class="px-3 py-2">Hand Cash</th>
          <th class="px-3 py-2">RTGS Cash</th>
          <th class="px-3 py-2">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr class="bg-emerald-50/60">
          <td class="px-3 py-2 font-medium text-slate-700">OB</td>
          <td class="px-3 py-2">{{ leftObCash.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ leftObRtgs.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ (leftObCash + leftObRtgs).toLocaleString() }}</td>
        </tr>
        <tr class="bg-rose-50/60">
          <td class="px-3 py-2 font-medium text-slate-700">CB</td>
          <td class="px-3 py-2">{{ fmt(leftCbCash) }}</td>
          <td class="px-3 py-2">{{ fmt(leftCbRtgs) }}</td>
          <td class="px-3 py-2">
            {{ leftCbCash === null || leftCbRtgs === null ? '—' : (leftCbCash + leftCbRtgs).toLocaleString() }}
          </td>
        </tr>
      </tbody>
    </table>

    <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
          <th class="px-3 py-2">{{ rightLabel }}</th>
          <th class="px-3 py-2">Hand Cash</th>
          <th class="px-3 py-2">RTGS Cash</th>
          <th class="px-3 py-2">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr class="bg-emerald-50/60">
          <td class="px-3 py-2 font-medium text-slate-700">OB</td>
          <td class="px-3 py-2">{{ rightObCash.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ rightObRtgs.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ (rightObCash + rightObRtgs).toLocaleString() }}</td>
        </tr>
        <tr class="bg-rose-50/60">
          <td class="px-3 py-2 font-medium text-slate-700">CB</td>
          <td class="px-3 py-2">{{ fmt(rightCbCash) }}</td>
          <td class="px-3 py-2">{{ fmt(rightCbRtgs) }}</td>
          <td class="px-3 py-2">
            {{ rightCbCash === null || rightCbRtgs === null ? '—' : (rightCbCash + rightCbRtgs).toLocaleString() }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
