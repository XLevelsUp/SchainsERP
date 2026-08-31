<script setup lang="ts">
import { AlertTriangle, PackageX } from 'lucide-vue-next'
import BaseModal from '@/components/ui/BaseModal.vue'

/*
|--------------------------------------------------------------------------
| Customer Delivery modal — schedules/tracks each customer's deliveries
|--------------------------------------------------------------------------
| Opened by CustomerContextPanel's "Customer Deliver" button. There is
| currently NO backend support for this at all — checked directly rather
| than trusting an old comment:
|   - app/Models/OrderDetail.php exists and points at an `order_details`
|     table (primary key order_id), but:
|   - No migration creates that table (grep across database/migrations
|     for "order" returns nothing).
|   - No controller references the OrderDetail model anywhere in app/.
|   - No route exposes it in routes/api.php.
|   - The table doesn't exist in the live Postgres DB, and isn't in
|     either reference SQL dump under schainbackend/db/.
| So this is an intentionally empty modal — a stub UI ready to fill in
| once the backend adds a real order_details migration + controller +
| routes, rather than fabricating columns/data with nothing to base them
| on. Same "report clearly, don't invent" approach as the other backend
| gaps flagged elsewhere on this page (Active Orders, REPLY ID, etc).
|--------------------------------------------------------------------------
*/

defineProps<{ customerName: string }>()
defineEmits<{ close: [] }>()
</script>

<template>
  <BaseModal :title="`Customer Delivery — ${customerName}`" @close="$emit('close')">
    <p class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      <span>
        <strong>Pending backend:</strong> there is no delivery-schedule/tracking API yet.
        <code class="rounded bg-amber-100 px-1 py-0.5">app/Models/OrderDetail.php</code> exists on
        the backend, but its <code class="rounded bg-amber-100 px-1 py-0.5">order_details</code>
        table has no migration, no controller reads or writes it, and no route exposes it — the
        table doesn't exist in the database at all. This modal is a placeholder until the backend
        adds a real migration, controller, and routes for it.
      </span>
    </p>

    <div class="flex flex-col items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-10 text-center">
      <PackageX class="h-8 w-8 text-slate-300" />
      <p class="text-sm font-medium text-slate-600">No delivery data available</p>
      <p class="text-xs text-slate-400">This will show scheduled and past deliveries for this customer once the API exists.</p>
    </div>
  </BaseModal>
</template>
