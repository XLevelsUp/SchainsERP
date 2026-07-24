<script setup lang="ts">
import { ref } from 'vue'
import { Search, Plus } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import DataTable from '@/components/ui/DataTable.vue'
import type { DataTableColumn, Client, ClientStatus } from '@/types'

const searchQuery = ref('')

const columns: DataTableColumn<Client>[] = [
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'status', label: 'Status' },
  { key: 'createdAt', label: 'Created' },
]

const clients: Client[] = [
  {
    id: '1',
    name: 'Acme Manufacturing',
    email: 'ops@acme.example',
    status: 'active',
    createdAt: '2026-03-12',
  },
  {
    id: '2',
    name: 'Northwind Traders',
    email: 'accounts@northwind.example',
    status: 'pending',
    createdAt: '2026-05-02',
  },
  {
    id: '3',
    name: 'Globex Logistics',
    email: 'hello@globex.example',
    status: 'inactive',
    createdAt: '2026-06-18',
  },
]

const statusStyles: Record<ClientStatus, string> = {
  active: 'bg-emerald-50 text-emerald-700',
  pending: 'bg-amber-50 text-amber-700',
  inactive: 'bg-slate-100 text-slate-600',
}
</script>

<template>
  <div>
    <PageHeader title="Clients" description="Manage the organizations you work with.">
      <template #actions>
        <BaseButton :icon="Plus">New client</BaseButton>
      </template>
    </PageHeader>

    <div class="mb-4 flex items-center gap-2">
      <div class="relative w-full max-w-xs">
        <Search
          class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
        />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search clients…"
          aria-label="Search clients"
          class="w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
        />
      </div>
    </div>

    <DataTable
      :columns="columns"
      :rows="clients"
      empty-message="No clients yet. Add your first client to get started."
    >
      <template #status="{ value }">
        <span
          class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
          :class="statusStyles[value as ClientStatus]"
        >
          {{ value }}
        </span>
      </template>
    </DataTable>
  </div>
</template>
