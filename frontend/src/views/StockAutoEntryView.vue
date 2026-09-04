<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Trash } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { stockApi } from '@/lib/stockApi'
import { userDetailsApi } from '@/lib/userDetailsApi'
import { itemsApi } from '@/lib/itemsApi'
import { fitemBoxesApi } from '@/lib/fitemBoxesApi'
import { userOptionLabel } from '@/lib/userLabel'
import { nowDateTimeInputValue, toBackendDateTime } from '@/lib/date'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type {
  AutoEntryItemInput,
  AutoEntryRowType,
  AutoEntryType,
  FitemBox,
  Item,
  UserDetailListItem,
} from '@/types'

/*
|--------------------------------------------------------------------------
| Stock Auto Entry — POST /stock/auto-entry (PR #30)
|--------------------------------------------------------------------------
| Direct transfer of stock between two users. One submission writes one
| billing entry plus one OUT stock_details row per item row, and moves both
| parties' running balances.
|
| Its own screen rather than a StockManagementView modal because it carries
| a full party/item header of its own and ignores that page's selected user
| entirely.
|
| Four transaction types name the same four slots differently
| (from_employee vs from_employee1 vs from_head vs from_head1, and so on).
| That mapping lives in stockApi::toAutoEntryPartyPayload — this view works
| in neutral from/to terms and only varies the labels.
|
| Items carry NO item id: AutoEntryService resolves from_item/to_item once
| from the header and applies them to every row. So the grid is weights and
| touches only.
|
| Row type switches the maths the server does, and the previews below mirror
| it exactly:
|   NORMAL/FITEM  waste = grams x waste% / 100
|                 purity = grams x touch / 100 + waste
|   GMS           net = grams - (stone + thread)
|                 waste = net x waste% / 100
|                 purity = (net + waste) x touch / 100
| Only the inputs are posted; the server derives the authoritative purity
| and waste values (same contract as every other stock screen here).
|
| Touch bounds are 1-999 on this endpoint, NOT 0-100 like the rest of the
| app. That is AutoEntryRequest's own rule, not a typo here.
|
| waste_id/to_waste_id are never sent — they must exist in wastage_details
| and no endpoint lists that table (same gap StockOutPanel documents).
|
| KNOWN BACKEND GAP: the date/time below is validated but never applied.
| AutoEntryRequest validates items.*.added_at, yet executeAutoTransfer only
| reads a TOP-LEVEL $data['added_at'] which has no validation rule, so
| $request->validated() strips it and every row is stamped now(). We send
| the per-row value anyway so the screen is correct the moment that is
| fixed. Tracked in PENDING_WORK.md.
|--------------------------------------------------------------------------
*/

const auth = useAuthStore()
const toast = useToastStore()

interface TypeMeta {
  label: string
  fromUserLabel: string
  toUserLabel: string
  fromItemLabel: string
  toItemLabel: string
  fromRetailer: boolean
  toRetailer: boolean
}

// Which retailer slots each type accepts comes straight from
// AutoEntryRequest — sending one the type does not declare is silently
// dropped by validated(), so the inputs are hidden instead of ignored.
const TYPE_META: Record<AutoEntryType, TypeMeta> = {
  EMPTOEMP: {
    label: 'Employee → Employee',
    fromUserLabel: 'From employee',
    toUserLabel: 'To employee',
    fromItemLabel: 'From employee item',
    toItemLabel: 'To employee item',
    fromRetailer: true,
    toRetailer: true,
  },
  EMPTOHEAD: {
    label: 'Employee → Head',
    fromUserLabel: 'From employee',
    toUserLabel: 'To head',
    fromItemLabel: 'Employee item',
    toItemLabel: 'Head item',
    fromRetailer: true,
    toRetailer: false,
  },
  ANOTHERHEADTOEMP: {
    label: 'Head → Employee',
    fromUserLabel: 'From head',
    toUserLabel: 'To employee',
    fromItemLabel: 'Head item',
    toItemLabel: 'Employee item',
    fromRetailer: false,
    toRetailer: true,
  },
  HEADTOHEAD: {
    label: 'Head → Head',
    fromUserLabel: 'From head',
    toUserLabel: 'To head',
    fromItemLabel: 'From head item',
    toItemLabel: 'To head item',
    fromRetailer: false,
    toRetailer: false,
  },
}

const typeOptions = (Object.keys(TYPE_META) as AutoEntryType[]).map((value) => ({
  value,
  label: TYPE_META[value].label,
}))

const rowTypeOptions: { value: AutoEntryRowType; label: string }[] = [
  { value: 'NORMAL', label: 'Normal' },
  { value: 'GMS', label: 'GMS (goldsmith)' },
  { value: 'FITEM', label: 'Finished item' },
]

const TOUCH_MIN = 1
const TOUCH_MAX = 999

function makeEmptyRow(): AutoEntryItemInput {
  return {
    type: 'NORMAL',
    grams: null,
    touch: null,
    to_touch: null,
    waste_total: null,
    to_waste_total: null,
    remarks: '',
    item_remarks: '',
    added_at: nowDateTimeInputValue(),
    stone: null,
    thread: null,
    to_stone: null,
    to_thread: null,
    gms_mtouch: null,
    gms_mthouch_wastage: null,
    to_gms_mtouch: null,
    to_gms_mthouch_wastage: null,
    box_id: null,
    mtouch: null,
    to_mtouch: null,
  }
}

const form = reactive({
  type: 'EMPTOEMP' as AutoEntryType,
  from_user_id: null as number | null,
  to_user_id: null as number | null,
  from_item_id: null as number | null,
  to_item_id: null as number | null,
  from_retailer_id: null as number | null,
  to_retailer_id: null as number | null,
})

const rows = ref<AutoEntryItemInput[]>([makeEmptyRow()])
const isSaving = ref(false)
const fieldErrors = ref<Record<string, string>>({})

const meta = computed(() => TYPE_META[form.type])

const users = ref<UserDetailListItem[]>([])
const items = ref<Item[]>([])
const boxes = ref<FitemBox[]>([])

const userOptions = computed(() =>
  users.value.map((u) => ({ value: u.id, label: userOptionLabel(u) })),
)
const itemOptions = computed(() =>
  items.value.map((i) => ({ value: i.item_id, label: i.item_name })),
)
const boxOptions = computed(() =>
  boxes.value.map((b) => ({ value: b.box_id, label: b.box_name })),
)

// Clearing the retailer slots the new type does not accept keeps a value
// from a previous selection out of the payload.
function onTypeChange(next: AutoEntryType) {
  form.type = next
  if (!TYPE_META[next].fromRetailer) form.from_retailer_id = null
  if (!TYPE_META[next].toRetailer) form.to_retailer_id = null
}

function addRow() {
  rows.value.push(makeEmptyRow())
}
function removeRow(index: number) {
  rows.value.splice(index, 1)
}

// ---------------------------------------------------------------------------
// Preview maths — mirrors AutoEntryService exactly (see the header note).
// ---------------------------------------------------------------------------

function netGramsFor(row: AutoEntryItemInput, side: 'from' | 'to'): number {
  const grams = row.grams ?? 0
  if (row.type !== 'GMS') return grams
  const stone = (side === 'from' ? row.stone : row.to_stone) ?? 0
  const thread = (side === 'from' ? row.thread : row.to_thread) ?? 0
  return grams - (stone + thread)
}

function wasteValueFor(row: AutoEntryItemInput, side: 'from' | 'to'): number {
  const pct = (side === 'from' ? row.waste_total : row.to_waste_total) ?? 0
  return (netGramsFor(row, side) * pct) / 100
}

function purityFor(row: AutoEntryItemInput, side: 'from' | 'to'): number {
  const touch = (side === 'from' ? row.touch : row.to_touch) ?? 0
  const waste = wasteValueFor(row, side)
  if (row.type === 'GMS') {
    return ((netGramsFor(row, side) + waste) * touch) / 100
  }
  return ((row.grams ?? 0) * touch) / 100 + waste
}

function formatNumber(value: number): string {
  return Number.isFinite(value) ? value.toFixed(4) : '0.0000'
}

const totals = computed(() => {
  let grams = 0
  let purity = 0
  let toPurity = 0
  for (const row of rows.value) {
    grams += row.grams ?? 0
    purity += purityFor(row, 'from')
    toPurity += purityFor(row, 'to')
  }
  return { grams, purity, toPurity }
})

// ---------------------------------------------------------------------------
// Validation — mirrors AutoEntryRequest
// ---------------------------------------------------------------------------

function validate(): boolean {
  fieldErrors.value = {}

  if (form.from_user_id === null) fieldErrors.value.from_user_id = `${meta.value.fromUserLabel} is required.`
  if (form.to_user_id === null) fieldErrors.value.to_user_id = `${meta.value.toUserLabel} is required.`
  if (
    form.from_user_id !== null &&
    form.from_user_id === form.to_user_id
  ) {
    fieldErrors.value.to_user_id = 'Sender and receiver must be different users.'
  }
  if (form.from_item_id === null) fieldErrors.value.from_item_id = `${meta.value.fromItemLabel} is required.`
  if (form.to_item_id === null) fieldErrors.value.to_item_id = `${meta.value.toItemLabel} is required.`

  if (rows.value.length === 0) fieldErrors.value.items = 'Add at least one item.'

  rows.value.forEach((row, index) => {
    const label = `Row ${index + 1}`
    if (row.grams === null || row.grams <= 0) {
      fieldErrors.value[`${index}.grams`] = `${label}: grams must be greater than 0.`
    }
    if (row.touch === null || row.touch < TOUCH_MIN || row.touch > TOUCH_MAX) {
      fieldErrors.value[`${index}.touch`] = `${label}: touch must be between ${TOUCH_MIN} and ${TOUCH_MAX}.`
    }
    if (row.to_touch === null || row.to_touch < TOUCH_MIN || row.to_touch > TOUCH_MAX) {
      fieldErrors.value[`${index}.to_touch`] =
        `${label}: to-touch must be between ${TOUCH_MIN} and ${TOUCH_MAX}.`
    }
    if (row.type === 'GMS' && netGramsFor(row, 'from') <= 0) {
      fieldErrors.value[`${index}.stone`] =
        `${label}: stone + thread cannot be greater than or equal to grams.`
    }
  })

  const firstError = Object.values(fieldErrors.value)[0]
  if (firstError) {
    toast.show(firstError, 'error')
    return false
  }
  return true
}

function resetForm() {
  form.from_user_id = null
  form.to_user_id = null
  form.from_item_id = null
  form.to_item_id = null
  form.from_retailer_id = null
  form.to_retailer_id = null
  rows.value = [makeEmptyRow()]
  fieldErrors.value = {}
}

async function handleSubmit() {
  if (isSaving.value) return
  if (!validate()) return

  const actingUserId = auth.user?.user_id
  if (actingUserId === undefined) {
    toast.show('Your session has no user id — sign in again.', 'error')
    return
  }

  isSaving.value = true
  try {
    const result = await stockApi.postAutoEntry(
      {
        type: form.type,
        from_user_id: form.from_user_id,
        to_user_id: form.to_user_id,
        from_item_id: form.from_item_id,
        to_item_id: form.to_item_id,
        from_retailer_id: form.from_retailer_id,
        to_retailer_id: form.to_retailer_id,
        items: rows.value.map((row) => ({
          ...row,
          added_at: toBackendDateTime(row.added_at),
        })),
      },
      actingUserId,
    )
    toast.show(`Auto entry recorded — ${result.length} row(s).`, 'success')
    resetForm()
  } catch (err) {
    if (err instanceof ApiError) {
      toast.show(err.errors ? (Object.values(err.errors)[0]?.[0] ?? err.message) : err.message, 'error')
    } else {
      toast.show('Failed to record the auto entry.', 'error')
    }
  } finally {
    isSaving.value = false
  }
}

async function loadLookups() {
  try {
    const [usersData, itemsData, boxesData] = await Promise.all([
      userDetailsApi.list(undefined, 'stock'),
      itemsApi.list(),
      fitemBoxesApi.list(),
    ])
    users.value = usersData
    items.value = itemsData
    boxes.value = boxesData
  } catch {
    // Non-fatal — the pickers just offer fewer options.
  }
}

onMounted(loadLookups)
</script>

<template>
  <div>
    <PageHeader
      title="Stock Auto Entry"
      description="Record a direct stock transfer between two users."
    />

    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <BaseCard>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Transfer</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <BaseSelect
            :model-value="form.type"
            label="Transaction type"
            size="sm"
            :options="typeOptions"
            @update:model-value="(v) => onTypeChange(v as AutoEntryType)"
          />
          <BaseSelect
            v-model="form.from_user_id"
            :label="meta.fromUserLabel"
            size="sm"
            required
            placeholder="Select…"
            :options="userOptions"
            :error="fieldErrors.from_user_id"
          />
          <BaseSelect
            v-model="form.to_user_id"
            :label="meta.toUserLabel"
            size="sm"
            required
            placeholder="Select…"
            :options="userOptions"
            :error="fieldErrors.to_user_id"
          />
          <BaseSelect
            v-model="form.from_item_id"
            :label="meta.fromItemLabel"
            size="sm"
            required
            placeholder="Select…"
            :options="itemOptions"
            :error="fieldErrors.from_item_id"
          />
          <BaseSelect
            v-model="form.to_item_id"
            :label="meta.toItemLabel"
            size="sm"
            required
            placeholder="Select…"
            :options="itemOptions"
            :error="fieldErrors.to_item_id"
          />
          <BaseSelect
            v-if="meta.fromRetailer"
            v-model="form.from_retailer_id"
            label="From retailer (optional)"
            size="sm"
            placeholder="None…"
            :options="userOptions"
          />
          <BaseSelect
            v-if="meta.toRetailer"
            v-model="form.to_retailer_id"
            label="To retailer (optional)"
            size="sm"
            placeholder="None…"
            :options="userOptions"
          />
        </div>

        <p class="mt-3 text-xs text-slate-500">
          Every item row below uses this pair of items — the endpoint applies one from-item and
          one to-item to the whole transfer.
        </p>
      </BaseCard>

      <BaseCard>
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">Items</h2>
          <BaseButton variant="secondary" type="button" :icon="Plus" @click="addRow">
            Add item
          </BaseButton>
        </div>
        <p v-if="fieldErrors.items" class="mb-2 text-sm text-red-600">{{ fieldErrors.items }}</p>

        <div
          v-for="(row, index) in rows"
          :key="index"
          class="mb-3 rounded-lg border border-slate-200 p-3"
        >
          <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
            <BaseSelect
              :model-value="row.type"
              label="Row type"
              size="sm"
              :options="rowTypeOptions"
              @update:model-value="(v) => (row.type = v as AutoEntryRowType)"
            />
            <BaseInput
              v-model="row.added_at"
              label="Date-Time"
              type="datetime-local"
              size="sm"
            />
            <button
              type="button"
              class="mb-1 justify-self-start rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30"
              aria-label="Remove item"
              :disabled="rows.length === 1"
              @click="removeRow(index)"
            >
              <Trash class="h-4 w-4" />
            </button>
          </div>

          <div class="mt-2 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <BaseInput
              :model-value="row.grams === null ? '' : String(row.grams)"
              label="Grams"
              type="number"
              size="sm"
              :error="fieldErrors[`${index}.grams`]"
              @update:model-value="(v) => (row.grams = v === '' ? null : Number(v))"
            />
            <BaseInput
              :model-value="row.touch === null ? '' : String(row.touch)"
              label="Touch"
              type="number"
              size="sm"
              :error="fieldErrors[`${index}.touch`]"
              @update:model-value="(v) => (row.touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :model-value="row.to_touch === null ? '' : String(row.to_touch)"
              label="To touch"
              type="number"
              size="sm"
              :error="fieldErrors[`${index}.to_touch`]"
              @update:model-value="(v) => (row.to_touch = v === '' ? null : Number(v))"
            />
            <BaseInput
              :model-value="row.waste_total === null ? '' : String(row.waste_total)"
              label="Wastage %"
              type="number"
              size="sm"
              @update:model-value="(v) => (row.waste_total = v === '' ? null : Number(v))"
            />
            <BaseInput
              :model-value="row.to_waste_total === null ? '' : String(row.to_waste_total)"
              label="To wastage %"
              type="number"
              size="sm"
              @update:model-value="(v) => (row.to_waste_total = v === '' ? null : Number(v))"
            />
          </div>

          <div v-if="row.type === 'GMS'" class="mt-2 rounded-md bg-slate-50 p-3">
            <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              Goldsmith deductions
            </p>
            <div class="grid gap-3 sm:grid-cols-4">
              <BaseInput
                :model-value="row.stone === null ? '' : String(row.stone)"
                label="Stone"
                type="number"
                size="sm"
                :error="fieldErrors[`${index}.stone`]"
                @update:model-value="(v) => (row.stone = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="row.thread === null ? '' : String(row.thread)"
                label="Thread"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.thread = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="row.to_stone === null ? '' : String(row.to_stone)"
                label="To stone"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.to_stone = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="row.to_thread === null ? '' : String(row.to_thread)"
                label="To thread"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.to_thread = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="row.gms_mtouch === null ? '' : String(row.gms_mtouch)"
                label="M-touch"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.gms_mtouch = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="
                  row.gms_mthouch_wastage === null ? '' : String(row.gms_mthouch_wastage)
                "
                label="M-touch wastage"
                type="number"
                size="sm"
                @update:model-value="
                  (v) => (row.gms_mthouch_wastage = v === '' ? null : Number(v))
                "
              />
              <BaseInput
                :model-value="row.to_gms_mtouch === null ? '' : String(row.to_gms_mtouch)"
                label="To M-touch"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.to_gms_mtouch = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="
                  row.to_gms_mthouch_wastage === null ? '' : String(row.to_gms_mthouch_wastage)
                "
                label="To M-touch wastage"
                type="number"
                size="sm"
                @update:model-value="
                  (v) => (row.to_gms_mthouch_wastage = v === '' ? null : Number(v))
                "
              />
            </div>
          </div>

          <div v-if="row.type === 'FITEM'" class="mt-2 rounded-md bg-slate-50 p-3">
            <p class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
              Finished item
            </p>
            <div class="grid gap-3 sm:grid-cols-3">
              <BaseSelect
                v-model="row.box_id"
                label="Box (optional)"
                size="sm"
                placeholder="None…"
                :options="boxOptions"
              />
              <BaseInput
                :model-value="row.mtouch === null ? '' : String(row.mtouch)"
                label="M-touch"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.mtouch = v === '' ? null : Number(v))"
              />
              <BaseInput
                :model-value="row.to_mtouch === null ? '' : String(row.to_mtouch)"
                label="To M-touch"
                type="number"
                size="sm"
                @update:model-value="(v) => (row.to_mtouch = v === '' ? null : Number(v))"
              />
            </div>
          </div>

          <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <BaseInput v-model="row.remarks" label="Remarks (optional)" size="sm" />
            <BaseInput v-model="row.item_remarks" label="Item remarks (optional)" size="sm" />
          </div>

          <p class="mt-2 text-xs text-slate-500">
            Sender wastage
            <span class="font-medium text-slate-700 tabular-nums">
              {{ formatNumber(wasteValueFor(row, 'from')) }}
            </span>
            · purity
            <span class="font-medium text-slate-700 tabular-nums">
              {{ formatNumber(purityFor(row, 'from')) }}
            </span>
            — receiver wastage
            <span class="font-medium text-slate-700 tabular-nums">
              {{ formatNumber(wasteValueFor(row, 'to')) }}
            </span>
            · purity
            <span class="font-medium text-slate-700 tabular-nums">
              {{ formatNumber(purityFor(row, 'to')) }}
            </span>
            <span class="italic"> (preview — the server calculates the final values)</span>
          </p>
        </div>

        <div
          class="flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-slate-200 pt-3 text-sm"
        >
          <span class="text-slate-600">
            Grams:
            <span class="font-semibold text-slate-900 tabular-nums">
              {{ formatNumber(totals.grams) }}
            </span>
          </span>
          <span class="text-slate-600">
            Sender purity:
            <span class="font-semibold text-slate-900 tabular-nums">
              {{ formatNumber(totals.purity) }}
            </span>
          </span>
          <span class="text-slate-600">
            Receiver purity:
            <span class="font-semibold text-slate-900 tabular-nums">
              {{ formatNumber(totals.toPurity) }}
            </span>
          </span>
        </div>
      </BaseCard>

      <div class="flex items-center justify-end gap-3">
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="resetForm">
          Clear
        </BaseButton>
        <BaseButton type="submit" :disabled="isSaving">
          {{ isSaving ? 'Recording…' : 'Record auto entry' }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>
