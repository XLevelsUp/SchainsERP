<script setup lang="ts">
import AppSidebar from './AppSidebar.vue'
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-slate-50 lg:flex-row">
    <AppSidebar />
    <main class="flex-1 overflow-y-auto p-6">
      <RouterView />
    </main>
  </div>
</template>
