<script setup lang="ts">
import { computed } from 'vue'

// OB (opening balance, fetched live) / CB (closing balance, calculated
// client-side from the form's amount + payment method) for both parties —
// matches the legacy "Add Expense" dialog's balance tables. CB is null
// until there's enough on the form to compute it (no amount entered yet);
// shown as "—" rather than the legacy dialog's broken "NaN".
//
// Right side is optional — omit it (e.g. Auto Entry, where only one
// party's balance ever moves) to render a single full-width table instead
// of the two-party comparison.
const props = withDefaults(
  defineProps<{
    leftLabel: string
    leftObCash: number
    leftObRtgs: number
    leftCbCash: number | null
    leftCbRtgs: number | null
    // Grams/Purity are optional — pass all four *Grams/*Purity props to add
    // a gold columns (gold-conversion screens); omit them for cash-only
    // screens and the columns don't render at all. Gold screens collapse
    // Hand Cash/RTGS Cash down to one Cash total — the Payment Sources
    // section on those forms already itemizes the cash/bank split, so
    // repeating it here would just be noise.
    leftObGrams?: number
    leftObPurity?: number
    leftCbGrams?: number | null
    leftCbPurity?: number | null
    rightLabel?: string
    rightObCash?: number
    rightObRtgs?: number
    rightCbCash?: number | null
    rightCbRtgs?: number | null
    rightObGrams?: number
    rightObPurity?: number
    rightCbGrams?: number | null
    rightCbPurity?: number | null
    isLoading?: boolean
    // Which balance column the pending transaction will move — lets the
    // operator see at a glance whether Hand Cash or RTGS Cash is affected
    // before submitting, without changing the underlying OB/CB numbers.
    // Ignored on gold screens (no Hand Cash/RTGS Cash split there).
    activeColumn?: 'cash' | 'rtgs' | null
  }>(),
  { isLoading: false, activeColumn: null, rightLabel: undefined },
)

const hasRight = computed(() => props.rightLabel !== undefined)
const showGold = computed(() => props.leftObGrams !== undefined)

function fmt(value: number | null | undefined) {
  return value === null || value === undefined ? '—' : value.toLocaleString()
}
</script>

<template>
  <div v-if="isLoading" class="grid gap-4" :class="hasRight ? 'sm:grid-cols-2' : ''">
    <div
      v-for="n in hasRight ? 2 : 1"
      :key="n"
      class="animate-pulse overflow-hidden rounded-lg border border-slate-200"
    >
      <div class="h-8 bg-slate-100" />
      <div class="h-8 border-t border-slate-100 bg-slate-50" />
      <div class="h-8 border-t border-slate-100 bg-slate-50" />
    </div>
  </div>
  <div v-else class="grid gap-4" :class="hasRight ? 'sm:grid-cols-2' : ''">
    <table class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
          <th class="px-3 py-2">{{ leftLabel }}</th>
          <template v-if="!showGold">
            <th class="px-3 py-2 text-right" :class="activeColumn === 'cash' ? 'text-brand-700' : ''">
              Hand Cash
            </th>
            <th class="px-3 py-2 text-right" :class="activeColumn === 'rtgs' ? 'text-brand-700' : ''">
              RTGS Cash
            </th>
          </template>
          <th class="px-3 py-2 text-right">{{ showGold ? 'Cash' : 'Total' }}</th>
          <template v-if="showGold">
            <th class="px-3 py-2 text-right">Grams</th>
            <th class="px-3 py-2 text-right">Purity</th>
          </template>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr>
          <td class="px-3 py-2 font-medium text-slate-500">OB</td>
          <template v-if="!showGold">
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'cash' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ leftObCash.toLocaleString() }}
            </td>
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'rtgs' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ leftObRtgs.toLocaleString() }}
            </td>
          </template>
          <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-700">
            {{ (leftObCash + leftObRtgs).toLocaleString() }}
          </td>
          <template v-if="showGold">
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ fmt(leftObGrams) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ fmt(leftObPurity) }}</td>
          </template>
        </tr>
        <tr class="bg-rose-50/50">
          <td class="px-3 py-2 font-medium text-slate-500">CB</td>
          <template v-if="!showGold">
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'cash' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ fmt(leftCbCash) }}
            </td>
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'rtgs' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ fmt(leftCbRtgs) }}
            </td>
          </template>
          <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">
            {{ leftCbCash === null || leftCbRtgs === null ? '—' : (leftCbCash + leftCbRtgs).toLocaleString() }}
          </td>
          <template v-if="showGold">
            <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">{{ fmt(leftCbGrams) }}</td>
            <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">{{ fmt(leftCbPurity) }}</td>
          </template>
        </tr>
      </tbody>
    </table>

    <table v-if="hasRight" class="w-full min-w-[260px] overflow-hidden rounded-lg border border-slate-200 text-sm">
      <thead>
        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-600">
          <th class="px-3 py-2">{{ rightLabel }}</th>
          <template v-if="!showGold">
            <th class="px-3 py-2 text-right" :class="activeColumn === 'cash' ? 'text-brand-700' : ''">
              Hand Cash
            </th>
            <th class="px-3 py-2 text-right" :class="activeColumn === 'rtgs' ? 'text-brand-700' : ''">
              RTGS Cash
            </th>
          </template>
          <th class="px-3 py-2 text-right">{{ showGold ? 'Cash' : 'Total' }}</th>
          <template v-if="showGold">
            <th class="px-3 py-2 text-right">Grams</th>
            <th class="px-3 py-2 text-right">Purity</th>
          </template>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr>
          <td class="px-3 py-2 font-medium text-slate-500">OB</td>
          <template v-if="!showGold">
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'cash' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ fmt(rightObCash) }}
            </td>
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'rtgs' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ fmt(rightObRtgs) }}
            </td>
          </template>
          <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-700">
            {{ rightObCash == null || rightObRtgs == null ? '—' : (rightObCash + rightObRtgs).toLocaleString() }}
          </td>
          <template v-if="showGold">
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ fmt(rightObGrams) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ fmt(rightObPurity) }}</td>
          </template>
        </tr>
        <tr class="bg-emerald-50/50">
          <td class="px-3 py-2 font-medium text-slate-500">CB</td>
          <template v-if="!showGold">
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'cash' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ fmt(rightCbCash) }}
            </td>
            <td
              class="px-3 py-2 text-right tabular-nums"
              :class="activeColumn === 'rtgs' ? 'font-semibold text-slate-900' : 'text-slate-700'"
            >
              {{ fmt(rightCbRtgs) }}
            </td>
          </template>
          <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">
            {{ rightCbCash == null || rightCbRtgs == null ? '—' : (rightCbCash + rightCbRtgs).toLocaleString() }}
          </td>
          <template v-if="showGold">
            <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">{{ fmt(rightCbGrams) }}</td>
            <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900">{{ fmt(rightCbPurity) }}</td>
          </template>
        </tr>
      </tbody>
    </table>
  </div>
</template>
