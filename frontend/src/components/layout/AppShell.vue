<script setup lang="ts">
import AppTopbar from './AppTopbar.vue'
</script>

<template>
  <div class="flex h-screen flex-col overflow-hidden bg-slate-50">
    <AppTopbar />
    <main class="flex-1 overflow-y-auto p-6">
      <RouterView />
    </main>
  </div>
</template>
