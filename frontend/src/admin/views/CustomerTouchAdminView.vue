<script setup lang="ts">
import CustomerTouchView from '@/views/CustomerTouchView.vue'
import { customerTouchApi } from '../lib/resources'

// Customer Touch in the admin console (2026-11-24): the heads' screen on the
// console's client (POST/PUT/DELETE /admin/customer-touch, audit-logged).
</script>

<template>
  <CustomerTouchView :api="customerTouchApi" />
</template>
