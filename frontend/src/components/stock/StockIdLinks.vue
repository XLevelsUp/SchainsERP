<script setup lang="ts">
// The OUT / IN stock rows an Item Change or Item Conversion created, each a
// link to ID Wise (which opens ?stock_id= on arrival). `idWisePath` is
// '/id-wise-report' in the app and '/admin/id-wise-report' in the console.
defineProps<{
  outStockId: number | null
  inStockId: number | null
  idWisePath: string
}>()
</script>

<template>
  <span class="whitespace-nowrap tabular-nums">
    <template v-for="(id, index) in [outStockId, inStockId]" :key="index">
      <span v-if="index === 1" class="text-slate-400"> / </span>
      <RouterLink
        v-if="id"
        :to="{ path: idWisePath, query: { stock_id: id } }"
        class="text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
        :title="`Open stock #${id} in ID Wise`"
      >{{ id }}</RouterLink>
      <span v-else>—</span>
    </template>
  </span>
</template>
