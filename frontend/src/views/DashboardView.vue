<script setup lang="ts">
import type { Component } from 'vue'
import { Users, FileText, Activity, TrendingUp } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'

interface StatCard {
  label: string
  icon: Component
}

const stats: StatCard[] = [
  { label: 'Total clients', icon: Users },
  { label: 'Active pages', icon: FileText },
  { label: 'Open tasks', icon: Activity },
  { label: 'Monthly growth', icon: TrendingUp },
]
</script>

<template>
  <div>
    <PageHeader title="Dashboard" description="Overview of your workspace." />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <BaseCard v-for="stat in stats" :key="stat.label">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500">{{ stat.label }}</span>
          <component :is="stat.icon" class="h-4 w-4 text-slate-400" aria-hidden="true" />
        </div>
        <p class="mt-3 text-2xl font-semibold text-slate-900">—</p>
      </BaseCard>
    </div>

    <BaseCard class="mt-6">
      <h2 class="text-sm font-semibold text-slate-900">Recent activity</h2>
      <p class="mt-2 text-sm text-slate-500">No recent activity yet.</p>
    </BaseCard>
  </div>
</template>
