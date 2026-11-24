<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Hourglass } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'

// A menu entry whose page isn't built yet (2026-11-24): the company group's
// Roles History, Customers, Employees, Retailers, Customer FITEM, and the
// console's Salary. The title comes from the route's meta.
const route = useRoute()
const title = computed(() => String(route.meta.title ?? 'Coming soon'))
</script>

<template>
  <div>
    <PageHeader :title="title" />
    <div class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
      <Hourglass class="h-6 w-6 text-slate-400" aria-hidden="true" />
      <p class="text-sm font-medium text-slate-700">This page isn't built yet.</p>
      <p class="text-xs text-slate-500">It will be added once its screens are agreed.</p>
    </div>
  </div>
</template>
