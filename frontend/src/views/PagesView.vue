<script setup lang="ts">
import { FileText } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
</script>

<template>
  <div>
    <PageHeader title="Pages" description="Manage the content pages in your workspace." />

    <BaseCard>
      <div class="flex flex-col items-center gap-3 py-12 text-center">
        <FileText class="h-8 w-8 text-slate-300" aria-hidden="true" />
        <div>
          <p class="text-sm font-medium text-slate-900">No pages yet</p>
          <p class="mt-1 text-sm text-slate-500">Create your first page to start building content.</p>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
