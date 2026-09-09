<script setup lang="ts" generic="T extends object">
import type { DataTableColumn } from '@/types/table'

/*
| `size`, `flush` and `maxHeight` are all opt-in: omitting them renders
| exactly what this table has always rendered. `flush` drops the card
| chrome so the table can sit inside a panel that already has a border,
| and `maxHeight` turns the wrapper into its own scroll pane with a
| sticky header — used where two tables sit side by side and each needs
| to page independently.
*/
withDefaults(
  defineProps<{
    columns: DataTableColumn<T>[]
    rows: T[]
    emptyMessage?: string
    size?: 'md' | 'sm'
    flush?: boolean
    maxHeight?: string
  }>(),
  {
    emptyMessage: undefined,
    size: 'md',
    flush: false,
    maxHeight: undefined,
  },
)
</script>

<template>
  <div
    class="overflow-x-auto bg-white"
    :class="[
      flush ? '' : 'rounded-lg border border-slate-200',
      maxHeight ? 'overflow-y-auto' : '',
    ]"
    :style="maxHeight ? { maxHeight } : undefined"
  >
    <table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50">
        <tr>
          <th
            v-for="column in columns"
            :key="String(column.key)"
            scope="col"
            class="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase"
            :class="[
              size === 'sm' ? 'px-2.5 py-2' : 'px-4 py-3',
              maxHeight ? 'sticky top-0 z-10 shadow-[inset_0_-1px_0_#e2e8f0]' : '',
            ]"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        <tr v-if="rows.length === 0">
          <td
            :colspan="columns.length"
            class="text-center text-sm text-slate-500"
            :class="size === 'sm' ? 'px-2.5 py-8' : 'px-4 py-10'"
          >
            {{ emptyMessage ?? 'No data yet.' }}
          </td>
        </tr>
        <tr v-for="(row, index) in rows" :key="index" class="hover:bg-slate-50">
          <td
            v-for="column in columns"
            :key="String(column.key)"
            class="text-slate-700"
            :class="size === 'sm' ? 'px-2.5 py-1.5 text-[13px]' : 'px-4 py-3 text-sm'"
          >
            <slot :name="String(column.key)" :row="row" :value="row[column.key]">
              {{ row[column.key] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
