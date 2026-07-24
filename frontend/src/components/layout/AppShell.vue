<script setup lang="ts">
import { ref } from 'vue'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'

const isSidebarOpen = ref(false)

function toggleSidebar() {
  isSidebarOpen.value = !isSidebarOpen.value
}

function closeSidebar() {
  isSidebarOpen.value = false
}
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-slate-50">
    <AppSidebar :open="isSidebarOpen" @close="closeSidebar" />
    <div
      v-if="isSidebarOpen"
      class="fixed inset-0 z-20 bg-slate-900/40 lg:hidden"
      @click="closeSidebar"
    />
    <div class="flex flex-1 flex-col overflow-hidden">
      <AppTopbar @toggle-sidebar="toggleSidebar" />
      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
