<script setup lang="ts" generic="T extends object">
import type { DataTableColumn } from '@/types/table'

defineProps<{
  columns: DataTableColumn<T>[]
  rows: T[]
  emptyMessage?: string
}>()
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50">
        <tr>
          <th
            v-for="column in columns"
            :key="String(column.key)"
            scope="col"
            class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        <tr v-if="rows.length === 0">
          <td :colspan="columns.length" class="px-4 py-10 text-center text-sm text-slate-500">
            {{ emptyMessage ?? 'No data yet.' }}
          </td>
        </tr>
        <tr v-for="(row, index) in rows" :key="index" class="hover:bg-slate-50">
          <td v-for="column in columns" :key="String(column.key)" class="px-4 py-3 text-sm text-slate-700">
            <slot :name="String(column.key)" :row="row" :value="row[column.key]">
              {{ row[column.key] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
