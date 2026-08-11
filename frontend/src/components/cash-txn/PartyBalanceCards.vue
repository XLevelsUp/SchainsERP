<script setup lang="ts">
// Shows each party's current cash/RTGS balance before the transaction is
// submitted. The legacy dialog this is based on also showed a projected
// closing balance (CB) row, but that number depended on backend balance
// math this frontend doesn't replicate — showing a guessed CB risked being
// wrong, so this only shows what's actually known: the current balance.
defineProps<{
  leftLabel: string
  leftCash: number
  leftRtgs: number
  rightLabel: string
  rightCash: number
  rightRtgs: number
  isLoading?: boolean
}>()
</script>

<template>
  <div v-if="isLoading" class="text-sm text-slate-500">Loading balances…</div>
  <div v-else class="grid gap-4 sm:grid-cols-2">
    <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
          <th class="px-3 py-2">{{ leftLabel }}</th>
          <th class="px-3 py-2">Cash</th>
          <th class="px-3 py-2">RTGS</th>
          <th class="px-3 py-2">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="px-3 py-2 font-medium text-slate-700">Balance</td>
          <td class="px-3 py-2">{{ leftCash.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ leftRtgs.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ (leftCash + leftRtgs).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>

    <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
          <th class="px-3 py-2">{{ rightLabel }}</th>
          <th class="px-3 py-2">Cash</th>
          <th class="px-3 py-2">RTGS</th>
          <th class="px-3 py-2">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="px-3 py-2 font-medium text-slate-700">Balance</td>
          <td class="px-3 py-2">{{ rightCash.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ rightRtgs.toLocaleString() }}</td>
          <td class="px-3 py-2">{{ (rightCash + rightRtgs).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
