<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseCheckbox from '@/components/ui/BaseCheckbox.vue'
import BaseFileInput from '@/components/ui/BaseFileInput.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseMultiSelect from '@/components/ui/BaseMultiSelect.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { ApiError } from '@/lib/api'
import { isRoleActive } from '@/lib/rolesApi'
import { userCategoryOptions } from '@/lib/userCategory'
import { createUserDetailsApi } from '@/lib/userDetailsApi'
import type {
  CustomerTouch,
  Item,
  OpeningBalanceField,
  OpeningBalanceInput,
  Role,
  UserDetailFormValues,
  UserDetailListItem,
  UserFeatureFlagKey,
  UserFormProfile,
  UserFormProfileCatalog,
  UserFormSection,
} from '@/types'
import { adminApi, adminApiWithSpecialPassword } from '../../lib/adminApi'
import { userDetailsApi } from '../../lib/resources'
import {
  addDefaultPrimaryItem,
  applyRoleDefaults,
  FULL_USER_FORM_PROFILE,
  employeeIdsOf,
  flagGroupsFor,
  flagLabel,
  headIdsOf,
  makeEmptyUserForm,
  sameIds,
  userToForm,
} from '../../lib/userForm'
import { profileForRole } from '../../lib/userFormProfiles'
import { isValidPhone, PHONE_ERROR, PHONE_LENGTH, phoneDigits } from '@/lib/phone'
import { addDecimal, OPENING_FIELD_LABELS, pendingFor, unitOf } from '@/lib/openingBalance'
import { useToastStore } from '@/stores/toast'
import OpeningBalanceModal from './OpeningBalanceModal.vue'

/*
|--------------------------------------------------------------------------
| Admin console — the user form (create / edit), driven by the user's role
|--------------------------------------------------------------------------
| Always: role, name, user name, phone, password, category. Everything else
| comes from the role's profile (GET /admin/user-form-profiles, enforced on
| save by the backend): ADMIN follows the legacy admin user form; a role
| with no profile of its own gets the full form.
|
| - Role is picked on create and read-only on edit (the sections follow it).
| - Item totals and balances can't be typed in: transactions keep them. The
|   only manual change is the opening balance modal (Grams / Purity on each
|   item, GRAMS / PURITY / CASH / RTGS on the balances), which is recorded
|   and needs the special password (2026-11-24). An item that still holds a
|   balance can't be changed or removed.
| - Assigning heads needs the special password: on create when heads are
|   chosen, on edit only when the heads changed (then the whole set is sent).
|--------------------------------------------------------------------------
*/

const props = defineProps<{
  editingId: number | null
  roles: Role[]
  items: Item[]
  heads: UserDetailListItem[]
  // Every user, for a head's "Head users".
  users: UserDetailListItem[]
  touches: CustomerTouch[]
  catalog: UserFormProfileCatalog | null
  reloadItems?: () => unknown
  reloadHeads?: () => unknown
  reloadUsers?: () => unknown
  reloadTouches?: () => unknown
}>()

const emit = defineEmits<{ saved: [message: string]; close: [] }>()

const isEditing = computed(() => props.editingId !== null)
const toast = useToastStore()

const form = reactive<UserDetailFormValues>(makeEmptyUserForm())
const isLoadingUser = ref(false)
const loadError = ref('')
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const isSaving = ref(false)
const specialPassword = ref('')

// What the edited user had, for change detection and read-only displays.
const initialRoleId = ref<string>('')
const initialHeadIds = ref<number[]>([])
const initialCashHeadIds = ref<number[]>([])
const initialHeadUserIds = ref<number[]>([])
const balances = ref<{ grams: number; purity: number; cash: number; rtgs: number } | null>(null)
const currentImageUrl = ref<string | null>(null)
const currentProofUrl = ref<string | null>(null)
const currentLogoUrl = ref<string | null>(null)
const currentProofBackUrl = ref<string | null>(null)
const hasReportPassword = ref(false)
// The report password input stays closed until asked for (legacy "Edit").
const isChangingReportPassword = ref(false)

onMounted(async () => {
  if (props.editingId === null) return
  isLoadingUser.value = true
  try {
    const { user, profile_image_url, aadhar_image_url, image_url, aadhar_back_image_url } =
      await userDetailsApi.get(props.editingId)
    Object.assign(form, userToForm(user))
    savedItemIds.value = form.item_mappings.map((row) => row.item_id).filter((id): id is number => id !== null)
    initialRoleId.value = form.role_id
    initialHeadIds.value = headIdsOf(form.head_mappings)
    initialCashHeadIds.value = headIdsOf(form.cash_head_mappings)
    initialHeadUserIds.value = employeeIdsOf(form.head_user_mappings ?? [])
    hasReportPassword.value = Boolean(user.has_report_password)
    currentLogoUrl.value = image_url ?? null
    currentProofBackUrl.value = aadhar_back_image_url ?? null
    balances.value = {
      grams: Number(user.grams_grand_total ?? 0),
      purity: Number(user.purity_grand_total ?? 0),
      cash: Number(user.rak_cash_balance ?? 0),
      rtgs: Number(user.rak_rtgs_balance ?? 0),
    }
    currentImageUrl.value = profile_image_url
    currentProofUrl.value = aadhar_image_url
  } catch (err) {
    loadError.value = err instanceof ApiError ? err.message : 'Failed to load the user.'
  } finally {
    isLoadingUser.value = false
  }
})

// ---- the role's profile ----------------------------------------------------

const profile = computed<UserFormProfile | null>(() => {
  if (!form.role_id) return null
  // Profiles didn't load: show everything; the backend still enforces.
  if (!props.catalog) return FULL_USER_FORM_PROFILE
  return profileForRole(props.catalog, form.role_id)
})

function has(section: UserFormSection): boolean {
  return profile.value?.sections.includes(section) ?? false
}

// A role that doesn't sign in (CUSTOMER) has no Name or Password: the backend
// copies the user name into the name and sets a random password.
const signsIn = computed(() => profile.value?.signs_in !== false)

// A new user starts with the role's defaults (CUSTOMER: PURITY and GOLD;
// SALARY: GRAMS, GOLD, salary person YES, joining today). The admin can
// change them; editing never applies them. See applyRoleDefaults.
watch(profile, (next, previous) => {
  if (isEditing.value || !next) return
  applyRoleDefaults(form, next, previous ?? null, props.items)
  // Waiting opening balances the new role's form has no place for go.
  if (form.opening_balances?.length) {
    form.opening_balances = form.opening_balances.filter((e) =>
      next.sections.includes(e.item_id !== null ? 'items' : 'balances'),
    )
  }
})

// The items list can arrive after the role was picked: add the primary item
// then, on its first load only, so a row the admin removed stays removed.
watch(
  () => props.items,
  (next, previous) => {
    if (isEditing.value || !profile.value || previous?.length || !next.length) return
    addDefaultPrimaryItem(form, profile.value, next)
  },
)

const roleName = computed(
  () => props.roles.find((r) => String(r.id) === String(form.role_id))?.role ?? '—',
)

// Picked on create. On edit it can change too — the sections follow it —
// unless the user's current role is locked (ADMIN).
const roleLocked = computed(
  () =>
    isEditing.value &&
    (profileForRole(props.catalog, initialRoleId.value)?.lock_role_on_edit ?? false),
)

const roleOptions = computed(() =>
  props.roles
    .filter((r) => isRoleActive(r) || String(r.id) === initialRoleId.value)
    .map((r) => ({ value: String(r.id), label: r.role })),
)

// ---- options ---------------------------------------------------------------

const headOptions = computed(() =>
  props.heads
    .filter((u) => u.id !== props.editingId)
    .map((u) => ({ value: u.id, label: `${u.name} (HEAD)` })),
)

const itemOptions = computed(() =>
  props.items.map((item) => ({ value: item.item_id, label: item.item_name })),
)

const touchOptions = computed(() =>
  props.touches
    .filter(
      (t) =>
        t.is_active ||
        form.customer_touch_mappings?.some((m) => m.customer_touch_id === t.item_id),
    )
    .map((t) => ({ value: t.item_id, label: t.item_name })),
)

// ---- heads -----------------------------------------------------------------

const headIds = computed<number[]>({
  get: () => headIdsOf(form.head_mappings),
  set: (ids) => {
    form.head_mappings = ids.map((head_id) => ({ head_id }))
  },
})

// For a head: the users under them. The legacy form lists every user,
// the head included.
// A head's Head users: other HEAD users only, the head itself included
// (agreed 2026-11-23). The backend refuses anyone else, and saving never
// touches the head's links to employees and customers, which those users'
// own Head lists manage.
const headUserOptions = computed(() =>
  props.users
    .filter((u) => (u.type ?? '').toUpperCase() === 'HEAD')
    .map((u) => ({ value: u.id, label: `${u.name} (HEAD)` })),
)
const headUserIds = computed<number[]>({
  get: () => employeeIdsOf(form.head_user_mappings ?? []),
  set: (ids) => {
    form.head_user_mappings = ids.map((employee_id) => ({ employee_id }))
  },
})

const headsChanged = computed(() => !sameIds(headIdsOf(form.head_mappings), initialHeadIds.value))
const cashHeadsChanged = computed(
  () => !sameIds(headIdsOf(form.cash_head_mappings), initialCashHeadIds.value),
)
const headUsersChanged = computed(
  () => !sameIds(employeeIdsOf(form.head_user_mappings ?? []), initialHeadUserIds.value),
)
// Any head assignment going out needs the special password.
const sendsHeads = computed(
  () =>
    (has('heads') && headsChanged.value) ||
    (has('cash_heads') && cashHeadsChanged.value) ||
    (has('head_users') && headUsersChanged.value),
)
// A new user's opening balances need it too (an existing user's are saved
// from the modal, which asks for it there).
const needsSpecialPassword = computed(
  () => sendsHeads.value || (!isEditing.value && (form.opening_balances?.length ?? 0) > 0),
)

const salaryOptions = [
  { value: true, label: 'Yes' },
  { value: false, label: 'No' },
]

function addCashHead() {
  form.cash_head_mappings.push({ head_id: null })
}
function removeCashHead(index: number) {
  form.cash_head_mappings.splice(index, 1)
}

// ---- items -----------------------------------------------------------------

// Only loaded rows can hold a balance (new rows start at zero).
function holdsBalance(row: UserDetailFormValues['item_mappings'][number]): boolean {
  return Number(row.item_grams_total || 0) !== 0 || Number(row.item_purity_total || 0) !== 0
}

function addItem() {
  form.item_mappings.push({ item_id: null, item_grams_total: '0', item_purity_total: '0', is_primary: 0 })
}
function removeItem(index: number) {
  form.item_mappings.splice(index, 1)
}

// Including a new user's waiting opening balances.
const itemsGramsTotal = computed(() =>
  form.item_mappings.reduce((sum, row) => addDecimal(sum, rowFigure(row, 'ITEM_GRAMS')), '0'),
)
const itemsPurityTotal = computed(() =>
  form.item_mappings.reduce((sum, row) => addDecimal(sum, rowFigure(row, 'ITEM_PURITY')), '0'),
)

// ---- opening balance updates (2026-11-24) ------------------------------------
//
// Each item's Grams / Purity and the GRAMS / PURITY / CASH / RTGS balances
// open the opening balance modal (closing = previous + value).
//  - New user: the entry waits in form.opening_balances and is saved with
//    the user (the save then needs the special password). Figures on the
//    form include what's waiting; an item update moves its grand total too.
//  - Existing user: saved straight away (special password in the modal),
//    then the figures are refreshed from the response. Only for items the
//    user already had when the form opened: a newly added row has to be
//    saved first.

const pendingOpenings = computed(() => form.opening_balances ?? [])
// Item ids the edited user had when the form opened.
const savedItemIds = ref<number[]>([])

type RowFigure = 'ITEM_GRAMS' | 'ITEM_PURITY'
type MappingRow = UserDetailFormValues['item_mappings'][number]

function rowFigure(row: MappingRow, field: RowFigure): string {
  const base = field === 'ITEM_GRAMS' ? row.item_grams_total : row.item_purity_total
  return addDecimal(base, pendingFor(pendingOpenings.value, field, row.item_id))
}

const shownBalances = computed(() => {
  const base = balances.value ?? { grams: 0, purity: 0, cash: 0, rtgs: 0 }
  const pending = pendingOpenings.value
  return {
    grams: addDecimal(base.grams, pendingFor(pending, 'GRAMS')),
    purity: addDecimal(base.purity, pendingFor(pending, 'PURITY')),
    cash: addDecimal(base.cash, pendingFor(pending, 'CASH')),
    rtgs: addDecimal(base.rtgs, pendingFor(pending, 'RTGS')),
  }
})

// A waiting entry goes when its item leaves the list.
watch(
  () => form.item_mappings.map((row) => row.item_id),
  (ids) => {
    if (!form.opening_balances?.length) return
    form.opening_balances = form.opening_balances.filter((e) => e.item_id === null || ids.includes(e.item_id))
  },
)

function itemName(itemId: number | null): string {
  return props.items.find((i) => i.item_id === itemId)?.item_name ?? 'Item'
}

function canUpdateRow(row: MappingRow): boolean {
  if (row.item_id === null) return false
  return !isEditing.value || savedItemIds.value.includes(row.item_id)
}

const openingTarget = ref<{ field: OpeningBalanceField; itemId: number | null; title: string; previous: string } | null>(
  null,
)
const openingSubmitting = ref(false)
const openingError = ref('')

function openRowOpening(row: MappingRow, field: RowFigure) {
  if (!canUpdateRow(row)) return
  openingError.value = ''
  openingTarget.value = {
    field,
    itemId: row.item_id,
    title: `Opening balance — ${itemName(row.item_id)} ${field === 'ITEM_GRAMS' ? 'grams' : 'purity'}`,
    previous: rowFigure(row, field),
  }
}

const BALANCE_KEYS = { GRAMS: 'grams', PURITY: 'purity', CASH: 'cash', RTGS: 'rtgs' } as const

function openBalanceOpening(field: keyof typeof BALANCE_KEYS) {
  openingError.value = ''
  openingTarget.value = {
    field,
    itemId: null,
    title: `Opening balance — ${OPENING_FIELD_LABELS[field]}`,
    previous: shownBalances.value[BALANCE_KEYS[field]],
  }
}

function removePendingOpening(index: number) {
  form.opening_balances = pendingOpenings.value.filter((_, i) => i !== index)
}

function pendingLabel(entry: OpeningBalanceInput): string {
  const what = entry.item_id !== null ? `${itemName(entry.item_id)} ${OPENING_FIELD_LABELS[entry.field].toLowerCase()}` : OPENING_FIELD_LABELS[entry.field]
  const sign = entry.value.startsWith('-') ? '' : '+'
  return `${what}: ${sign}${entry.value} ${unitOf(entry.field)}`
}

async function saveOpening(input: { value: string; remarks: string; specialPassword: string }) {
  const target = openingTarget.value
  if (!target || openingSubmitting.value) return
  const entry: OpeningBalanceInput = { field: target.field, item_id: target.itemId, value: input.value, remarks: input.remarks }

  if (props.editingId === null) {
    form.opening_balances = [...pendingOpenings.value, entry]
    openingTarget.value = null
    return
  }

  openingSubmitting.value = true
  openingError.value = ''
  try {
    const result = await createUserDetailsApi(adminApiWithSpecialPassword(input.specialPassword)).openingBalance(
      props.editingId,
      entry,
    )
    const b = result.balances
    balances.value = {
      grams: Number(b.grams_grand_total),
      purity: Number(b.purity_grand_total),
      cash: Number(b.rak_cash_balance),
      rtgs: Number(b.rak_rtgs_balance),
    }
    if (b.item) {
      const row = form.item_mappings.find((r) => r.item_id === b.item?.item_id)
      if (row) {
        row.item_grams_total = b.item.item_grams_total
        row.item_purity_total = b.item.item_purity_total
      }
    }
    openingTarget.value = null
    toast.show(`Balance updated: closing ${result.update.closing_balance} ${unitOf(target.field)}.`, 'success')
  } catch (err) {
    if (err instanceof ApiError) {
      openingError.value = err.errors ? Object.values(err.errors)[0]?.[0] ?? err.message : err.message
    } else {
      openingError.value = 'Failed to update the balance.'
    }
  } finally {
    openingSubmitting.value = false
  }
}

// ---- customer touches ------------------------------------------------------

function addTouch() {
  form.customer_touch_mappings = [
    ...(form.customer_touch_mappings ?? []),
    { customer_touch_id: null, is_active: true },
  ]
}
function removeTouch(index: number) {
  form.customer_touch_mappings?.splice(index, 1)
}

// ---- images ----------------------------------------------------------------

// store()/update(): image|mimes:jpg,jpeg,png,webp|max:5120 (KB).
const MAX_IMAGE_BYTES = 5120 * 1024

const profileFiles = computed<File[]>({
  get: () => (form.profile_image ? [form.profile_image] : []),
  set: (files) => {
    form.profile_image = files[0] ?? null
  },
})
const proofFiles = computed<File[]>({
  get: () => (form.aadhar_image ? [form.aadhar_image] : []),
  set: (files) => {
    form.aadhar_image = files[0] ?? null
  },
})
const proofBackFiles = computed<File[]>({
  get: () => (form.aadhar_back_image ? [form.aadhar_back_image] : []),
  set: (files) => {
    form.aadhar_back_image = files[0] ?? null
  },
})
const imageFiles = computed<File[]>({
  get: () => (form.image_file ? [form.image_file] : []),
  set: (files) => {
    form.image_file = files[0] ?? null
  },
})

// ---- permissions -----------------------------------------------------------

const allowedFlags = computed<UserFeatureFlagKey[]>(() => profile.value?.flags ?? [])
// "Salary person" sits with the per-day salary when the role has that
// section, as on the legacy form, rather than among the switches.
const switchFlags = computed(() =>
  allowedFlags.value.filter((k) => !(k === 'is_salary_person' && has('salary'))),
)
// A short list (ADMIN six, HEAD fourteen) reads best flat, two columns, as
// the legacy forms have it; the full set keeps its groups.
const flatFlags = computed(() => switchFlags.value.length <= 16)
const flagGroups = computed(() => flagGroupsFor(switchFlags.value))
const enabledFlagCount = computed(() => switchFlags.value.filter((k) => form[k]).length)

function toggleGroup(keys: UserFeatureFlagKey[]) {
  const next = !keys.every((k) => form[k])
  for (const key of keys) form[key] = next
}

// ---- formatting ------------------------------------------------------------

const grams = (value: number | string) => Number(value || 0).toFixed(3)
const money = (value: number) =>
  value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ---- validation & save -----------------------------------------------------

function clearErrors() {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''
}

function validate(): boolean {
  clearErrors()

  const text = (value: string) => value.trim()
  if (!form.role_id) fieldErrors.role_id = 'Role is required.'
  if (signsIn.value && !text(form.name)) fieldErrors.name = 'Name is required.'
  else if (signsIn.value && text(form.name).length > 55) fieldErrors.name = 'Name must be 55 characters or fewer.'
  if (!text(form.user_name)) fieldErrors.user_name = 'User name is required.'
  else if (text(form.user_name).length > 55) fieldErrors.user_name = 'User name must be 55 characters or fewer.'
  if (!text(form.phone_no)) fieldErrors.phone_no = 'Phone number is required.'
  else if (!isValidPhone(form.phone_no)) fieldErrors.phone_no = PHONE_ERROR
  if (has('additional_phone') && text(form.additional_phone_no ?? '')) {
    const additional = text(form.additional_phone_no ?? '')
    if (!isValidPhone(additional)) fieldErrors.additional_phone_no = 'Additional phone number must be exactly 10 digits.'
    else if (additional === text(form.phone_no)) {
      fieldErrors.additional_phone_no = 'Additional phone number must differ from the phone number.'
    }
  }
  if (!form.category_name) fieldErrors.category_name = 'Category is required.'
  if (signsIn.value && !isEditing.value && form.password.trim().length < 6) {
    fieldErrors.password = 'Password is required (min 6 characters).'
  }

  if (has('contact')) {
    if (!text(form.address)) fieldErrors.address = 'Address is required.'
    else if (form.address.length > 400) fieldErrors.address = 'Address must be 400 characters or fewer.'
    if (form.signature.length > 45) fieldErrors.signature = 'Signature must be 45 characters or fewer.'
    if (form.mailing_name.length > 255) fieldErrors.mailing_name = 'Mailing name must be 255 characters or fewer.'
  }
  if (has('remarks') && form.remarks.length > 255) {
    fieldErrors.remarks = 'Remarks must be 255 characters or fewer.'
  }
  if (has('customer_comments') && form.customer_commants.length > 1500) {
    fieldErrors.customer_commants = 'Customer comments must be 1500 characters or fewer.'
  }

  if (has('items')) {
    const seen = new Set<number>()
    form.item_mappings.forEach((row, index) => {
      if (row.item_id === null) fieldErrors[`item_mappings.${index}`] = `Select an item for row ${index + 1} or remove it.`
      else if (seen.has(row.item_id)) fieldErrors[`item_mappings.${index}`] = 'This item is already listed.'
      else seen.add(row.item_id)
    })
  }
  if (has('cash_heads')) {
    form.cash_head_mappings.forEach((row, index) => {
      if (row.head_id === null) fieldErrors[`cash_head_mappings.${index}`] = `Select a cash head for row ${index + 1} or remove it.`
    })
  }
  if (has('customer_touches')) {
    const seen = new Set<number>()
    ;(form.customer_touch_mappings ?? []).forEach((row, index) => {
      if (row.customer_touch_id === null) fieldErrors[`customer_touch_mappings.${index}`] = `Select a touch for row ${index + 1} or remove it.`
      else if (seen.has(row.customer_touch_id)) fieldErrors[`customer_touch_mappings.${index}`] = 'This touch is already listed.'
      else seen.add(row.customer_touch_id)
    })
  }

  if (has('profile_image') && form.profile_image && form.profile_image.size > MAX_IMAGE_BYTES) {
    fieldErrors.profile_image = 'The image must be 5 MB or smaller.'
  }
  if (has('image') && form.image_file && form.image_file.size > MAX_IMAGE_BYTES) {
    fieldErrors.image_url = 'The image must be 5 MB or smaller.'
  }
  if (has('proof')) {
    if (form.aadhar_image && form.aadhar_image.size > MAX_IMAGE_BYTES) {
      fieldErrors.aadhar_image = 'The Aadhar front image must be 5 MB or smaller.'
    }
    if (form.aadhar_back_image && form.aadhar_back_image.size > MAX_IMAGE_BYTES) {
      fieldErrors.aadhar_back_image = 'The Aadhar back image must be 5 MB or smaller.'
    }
    if ((form.aadhar_number ?? '').trim().length > 20) {
      fieldErrors.aadhar_number = 'Aadhar number must be 20 characters or fewer.'
    }
  }
  if (has('salary') && form.per_day_salary) {
    const salary = Number(form.per_day_salary)
    if (!Number.isFinite(salary) || salary < 0) fieldErrors.per_day_salary = 'Per day salary must be a positive amount.'
  }
  if (has('report_password') && form.report_password && form.report_password.length < 4) {
    fieldErrors.report_password = 'Report password must be at least 4 characters.'
  }

  if (needsSpecialPassword.value && !specialPassword.value) {
    fieldErrors.special_password = sendsHeads.value
      ? 'Enter your special password to assign heads.'
      : 'Enter your special password to save the opening balances.'
  }

  const first = Object.values(fieldErrors)[0]
  if (first) {
    formError.value = first
    return false
  }
  return true
}

// A role that doesn't sign in has no Name on the form; its name is the user name.
function savedName(): string {
  return (signsIn.value ? form.name : form.user_name).trim()
}

async function handleSubmit() {
  if (isSaving.value || !validate()) return

  isSaving.value = true
  try {
    // A one-off client carrying the special password, only when heads or
    // opening balances go out.
    const api = createUserDetailsApi(
      needsSpecialPassword.value ? adminApiWithSpecialPassword(specialPassword.value) : adminApi,
    )
    // Sections the role doesn't have are left as they are: the backend drops
    // them anyway, and an image picked before switching roles isn't sent.
    const values: UserDetailFormValues = {
      ...form,
      profile_image: has('profile_image') ? form.profile_image : null,
      image_file: has('image') ? form.image_file : null,
      aadhar_image: has('proof') ? form.aadhar_image : null,
      aadhar_back_image: has('proof') ? form.aadhar_back_image : null,
      report_password: has('report_password') ? form.report_password : '',
    }
    if (props.editingId !== null) {
      await api.update(props.editingId, values, {
        includeItemMappings: has('items'),
        includeHeadMappings: sendsHeads.value,
      })
      emit('saved', `${savedName()} updated.`)
    } else {
      await api.create(values)
      emit('saved', `${savedName()} created.`)
    }
  } catch (err) {
    if (err instanceof ApiError) {
      formError.value = err.message
      for (const [key, messages] of Object.entries(err.errors ?? {})) fieldErrors[key] = messages[0]
      if (err.errors?.special_password) specialPassword.value = ''
    } else {
      formError.value = 'Failed to save the user.'
    }
  } finally {
    isSaving.value = false
  }
}

const errorFor = (prefix: string) =>
  Object.entries(fieldErrors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1]
</script>

<template>
  <BaseCard>
    <div class="mb-5 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
          aria-label="Back to users"
          @click="emit('close')"
        >
          <ArrowLeft class="h-4 w-4" />
        </button>
        <div>
          <h2 class="text-base font-semibold text-slate-900">
            {{ isEditing ? `Edit ${form.name || 'user'}` : 'New user' }}
          </h2>
          <p v-if="profile" class="text-xs text-slate-500">
            {{ profile.is_default ? 'Full form' : `${profile.role} form` }} — the fields follow the
            user's role.
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="loadError"
      class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ loadError }}
    </div>
    <p v-else-if="isLoadingUser" class="py-8 text-center text-sm text-slate-500">Loading user…</p>

    <form v-else class="flex flex-col gap-6" novalidate @submit.prevent="handleSubmit">
      <!-- Always -->
      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <BaseSelect
            v-if="!roleLocked"
            id="user_role"
            v-model="form.role_id"
            label="Role"
            required
            placeholder="Select a role…"
            :options="roleOptions"
            :error="fieldErrors.role_id"
          />
          <template v-else>
            <span class="mb-1 block text-sm font-medium text-slate-700">Role</span>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
              {{ roleName }}
            </div>
          </template>
        </div>
        <BaseInput
          v-if="signsIn"
          id="user_full_name"
          v-model="form.name"
          label="Name"
          required
          :error="fieldErrors.name"
        />
        <BaseInput
          id="user_login_name"
          v-model="form.user_name"
          label="User name"
          autocomplete="off"
          required
          :error="fieldErrors.user_name"
        />
        <BaseInput
          id="user_phone"
          v-model="form.phone_no"
          label="Phone no"
          required
          inputmode="numeric"
          :maxlength="PHONE_LENGTH"
          placeholder="10 digits"
          :sanitize="phoneDigits"
          :error="fieldErrors.phone_no"
        />
        <BaseInput
          v-if="has('additional_phone')"
          id="user_additional_phone"
          v-model="form.additional_phone_no"
          label="Additional phone no"
          inputmode="numeric"
          :maxlength="PHONE_LENGTH"
          placeholder="10 digits (optional)"
          :sanitize="phoneDigits"
          :error="fieldErrors.additional_phone_no"
        />
        <BaseInput
          v-if="signsIn"
          id="user_password"
          v-model="form.password"
          :label="isEditing ? 'New password' : 'Password'"
          type="password"
          autocomplete="new-password"
          :placeholder="isEditing ? 'Leave blank to keep the current one' : ''"
          :required="!isEditing"
          :error="fieldErrors.password"
        />
        <BaseSelect
          id="user_category"
          v-model="form.category_name"
          label="Category"
          required
          placeholder="Select…"
          :options="userCategoryOptions"
          :error="fieldErrors.category_name"
        />
      </section>

      <p v-if="!profile" class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-500">
        Pick a role to see the rest of the form — each role has its own fields and permissions.
      </p>

      <template v-else>
        <!-- Heads -->
        <section v-if="has('heads')" class="border-t border-slate-200 pt-5">
          <BaseMultiSelect
            id="user_heads"
            v-model="headIds"
            label="Head lists"
            placeholder="No heads — select one or more…"
            :options="headOptions"
            :reload="reloadHeads"
            :error="errorFor('head_mappings')"
          />
        </section>

        <!-- Head users (for a head: the users under them) -->
        <section v-if="has('head_users')" class="border-t border-slate-200 pt-5">
          <BaseMultiSelect
            id="user_head_users"
            v-model="headUserIds"
            label="Head users"
            placeholder="No heads — select the heads this head works with…"
            :options="headUserOptions"
            :reload="reloadUsers"
            :error="errorFor('head_user_mappings')"
          />
          <p class="mt-1 text-xs text-slate-500">
            Only HEAD users are listed. Employees and customers are linked to a head from their own
            Head lists. Changing this list needs your special password.
          </p>
        </section>

        <!-- Cash heads -->
        <section v-if="has('cash_heads')" class="border-t border-slate-200 pt-5">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Cash heads</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addCashHead">Add cash head</BaseButton>
          </div>
          <p v-if="!form.cash_head_mappings.length" class="text-sm text-slate-500">No cash heads.</p>
          <div v-for="(row, index) in form.cash_head_mappings" :key="index" class="mb-3 flex items-end gap-3">
            <BaseSelect
              v-model="row.head_id"
              label="Cash head"
              size="sm"
              class="flex-1"
              placeholder="Select a head…"
              :options="headOptions"
              :reload="reloadHeads"
              :error="fieldErrors[`cash_head_mappings.${index}`]"
            />
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove cash head"
              @click="removeCashHead(index)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
        </section>

        <!-- Customer comments -->
        <section v-if="has('customer_comments')" class="border-t border-slate-200 pt-5">
          <BaseTextarea
            id="user_customer_comments"
            v-model="form.customer_commants"
            label="Customer comments"
            :rows="2"
            :error="fieldErrors.customer_commants"
          />
        </section>

        <!-- Items -->
        <section v-if="has('items')" class="border-t border-slate-200 pt-5">
          <div class="mb-3 flex items-center justify-between">
            <div>
              <h3 class="text-sm font-semibold text-slate-900">Items</h3>
              <p class="text-xs text-slate-500">
                Totals change through transactions, or the Grams / Purity opening balance buttons
                (recorded). An item that holds a balance can't be changed or removed.
              </p>
            </div>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addItem">Add item</BaseButton>
          </div>
          <p v-if="!form.item_mappings.length" class="text-sm text-slate-500">No items assigned.</p>
          <div
            v-for="(row, index) in form.item_mappings"
            :key="index"
            class="mb-3 grid items-end gap-3 sm:grid-cols-[minmax(0,2fr)_auto_minmax(0,1fr)_minmax(0,1fr)_auto]"
          >
            <BaseSelect
              v-model="row.item_id"
              label="Item"
              size="sm"
              placeholder="Select…"
              :options="itemOptions"
              :reload="reloadItems"
              :disabled="holdsBalance(row)"
              :error="fieldErrors[`item_mappings.${index}`]"
            />
            <div class="pb-2">
              <BaseCheckbox
                :model-value="row.is_primary === 1"
                label="Primary"
                @update:model-value="row.is_primary = $event ? 1 : 0"
              />
            </div>
            <div>
              <span class="mb-1 block text-xs font-medium text-slate-600">Grams total (g)</span>
              <div class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right text-sm text-slate-700 tabular-nums">
                {{ grams(rowFigure(row, 'ITEM_GRAMS')) }}
              </div>
              <button
                type="button"
                class="mt-1 rounded border border-sky-200 bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!canUpdateRow(row)"
                :title="canUpdateRow(row) ? 'Opening balance update' : row.item_id === null ? 'Pick the item first' : 'Save the user first, then update this item'"
                :aria-label="`Update ${itemName(row.item_id)} grams`"
                @click="openRowOpening(row, 'ITEM_GRAMS')"
              >
                Grams
              </button>
            </div>
            <div>
              <span class="mb-1 block text-xs font-medium text-slate-600">Purity total (g)</span>
              <div class="rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-right text-sm text-slate-700 tabular-nums">
                {{ grams(rowFigure(row, 'ITEM_PURITY')) }}
              </div>
              <button
                type="button"
                class="mt-1 rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!canUpdateRow(row)"
                :title="canUpdateRow(row) ? 'Opening balance update' : row.item_id === null ? 'Pick the item first' : 'Save the user first, then update this item'"
                :aria-label="`Update ${itemName(row.item_id)} purity`"
                @click="openRowOpening(row, 'ITEM_PURITY')"
              >
                Purity
              </button>
            </div>
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-400"
              :aria-label="holdsBalance(row) ? 'Holds a balance — cannot be removed' : 'Remove item'"
              :title="holdsBalance(row) ? 'Holds a balance — cannot be removed' : undefined"
              :disabled="holdsBalance(row)"
              @click="removeItem(index)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
          <p v-if="fieldErrors.item_mappings" class="text-sm text-red-600">{{ fieldErrors.item_mappings }}</p>
        </section>

        <!-- Balances: changed only through the opening balance modal -->
        <section v-if="has('balances') && (!isEditing || balances)" class="border-t border-slate-200 pt-5">
          <h3 class="mb-3 text-sm font-semibold text-slate-900">Balances</h3>
          <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
              v-for="card in [
                { field: 'GRAMS', label: 'Grams grand total (g)', value: grams(shownBalances.grams) },
                { field: 'PURITY', label: 'Purity grand total (g)', value: grams(shownBalances.purity) },
                { field: 'CASH', label: 'Cash balance (₹)', value: money(Number(shownBalances.cash)) },
                { field: 'RTGS', label: 'RTGS balance (₹)', value: money(Number(shownBalances.rtgs)) },
              ] as const"
              :key="card.field"
              class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
            >
              <div class="flex items-center justify-between gap-2">
                <dt class="text-xs text-slate-500">{{ card.label }}</dt>
                <button
                  type="button"
                  class="rounded border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-red-700 hover:bg-red-100"
                  :aria-label="`Update ${card.label}`"
                  title="Opening balance update"
                  @click="openBalanceOpening(card.field)"
                >
                  {{ card.field }}
                </button>
              </div>
              <dd class="mt-1 text-right text-sm font-semibold text-slate-900 tabular-nums">{{ card.value }}</dd>
            </div>
          </dl>
          <div v-if="pendingOpenings.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
            <p class="mb-1 text-xs font-medium text-amber-800">Opening balances saved with the user:</p>
            <ul class="flex flex-col gap-1">
              <li
                v-for="(entry, index) in pendingOpenings"
                :key="index"
                class="flex items-center justify-between gap-2 text-xs text-amber-900 tabular-nums"
              >
                <span>{{ pendingLabel(entry) }}<template v-if="entry.remarks.trim()"> · {{ entry.remarks.trim() }}</template></span>
                <button
                  type="button"
                  class="rounded p-0.5 text-amber-700 hover:bg-amber-100"
                  :aria-label="`Remove ${pendingLabel(entry)}`"
                  @click="removePendingOpening(index)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </li>
            </ul>
          </div>
          <p
            v-if="has('items') && form.item_mappings.length"
            class="mt-2 text-xs text-slate-500 tabular-nums"
          >
            Items listed above: {{ grams(itemsGramsTotal) }} g · purity {{ grams(itemsPurityTotal) }} g
          </p>
        </section>

        <!-- Customer touches -->
        <section v-if="has('customer_touches')" class="border-t border-slate-200 pt-5">
          <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Customer touches</h3>
            <BaseButton variant="secondary" type="button" :icon="Plus" @click="addTouch">Add touch</BaseButton>
          </div>
          <p v-if="!form.customer_touch_mappings?.length" class="text-sm text-slate-500">No customer touches.</p>
          <div
            v-for="(row, index) in form.customer_touch_mappings"
            :key="index"
            class="mb-3 flex items-end gap-3"
          >
            <BaseSelect
              v-model="row.customer_touch_id"
              label="Touch"
              size="sm"
              class="flex-1"
              placeholder="Select a touch…"
              :options="touchOptions"
              :reload="reloadTouches"
              :error="fieldErrors[`customer_touch_mappings.${index}`] ?? fieldErrors[`customer_touch_mappings.${index}.customer_touch_id`]"
            />
            <div class="pb-2">
              <BaseCheckbox v-model="row.is_active" label="Active" />
            </div>
            <button
              type="button"
              class="mb-1 rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
              aria-label="Remove touch"
              @click="removeTouch(index)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
        </section>

        <!-- Contact (full form) -->
        <section v-if="has('contact')" class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2">
          <BaseTextarea
            id="user_address"
            v-model="form.address"
            label="Address"
            :rows="2"
            required
            :error="fieldErrors.address"
            class="sm:col-span-2"
          />
          <BaseInput id="user_signature" v-model="form.signature" label="Signature" :error="fieldErrors.signature" />
          <BaseInput
            id="user_mailing_name"
            v-model="form.mailing_name"
            label="Mailing name"
            placeholder="Defaults to the name"
            :error="fieldErrors.mailing_name"
          />
        </section>

        <section v-if="has('remarks')" class="border-t border-slate-200 pt-5">
          <BaseTextarea id="user_remarks" v-model="form.remarks" label="Remarks" :rows="2" :error="fieldErrors.remarks" />
        </section>

        <!-- Images -->
        <section
          v-if="has('image') || has('profile_image')"
          class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2"
        >
          <div v-if="has('image')">
            <BaseFileInput
              id="user_image_url"
              v-model="imageFiles"
              label="Image"
              accept="image/jpeg,image/png,image/webp"
              hint="JPG, PNG or WebP, up to 5 MB."
              :multiple="false"
              compact
            />
            <p v-if="currentLogoUrl && !form.image_file" class="mt-1 text-xs text-slate-500">
              Current:
              <a :href="currentLogoUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">view image</a>
            </p>
            <p v-if="fieldErrors.image_url" class="mt-1 text-xs text-red-600">{{ fieldErrors.image_url }}</p>
          </div>
          <div v-if="has('profile_image')">
            <BaseFileInput
              id="user_profile_picture"
              v-model="profileFiles"
              label="Profile picture"
              accept="image/jpeg,image/png,image/webp"
              hint="JPG, PNG or WebP, up to 5 MB."
              :multiple="false"
              compact
            />
            <p v-if="currentImageUrl && !form.profile_image" class="mt-1 text-xs text-slate-500">
              Current:
              <a :href="currentImageUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">view picture</a>
            </p>
            <p v-if="fieldErrors.profile_image" class="mt-1 text-xs text-red-600">{{ fieldErrors.profile_image }}</p>
          </div>
        </section>

        <!-- Aadhar -->
        <section v-if="has('proof')" class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-3">
          <BaseInput
            id="user_aadhar_number"
            v-model="form.aadhar_number"
            label="Aadhar number"
            autocomplete="off"
            :error="fieldErrors.aadhar_number"
          />
          <div>
            <BaseFileInput
              id="user_aadhar_front"
              v-model="proofFiles"
              label="Aadhar front"
              accept="image/jpeg,image/png,image/webp"
              :multiple="false"
              compact
            />
            <p v-if="currentProofUrl && !form.aadhar_image" class="mt-1 text-xs text-slate-500">
              Current:
              <a :href="currentProofUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">view front</a>
            </p>
            <p v-if="fieldErrors.aadhar_image" class="mt-1 text-xs text-red-600">{{ fieldErrors.aadhar_image }}</p>
          </div>
          <div>
            <BaseFileInput
              id="user_aadhar_back"
              v-model="proofBackFiles"
              label="Aadhar back"
              accept="image/jpeg,image/png,image/webp"
              :multiple="false"
              compact
            />
            <p v-if="currentProofBackUrl && !form.aadhar_back_image" class="mt-1 text-xs text-slate-500">
              Current:
              <a :href="currentProofBackUrl" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">view back</a>
            </p>
            <p v-if="fieldErrors.aadhar_back_image" class="mt-1 text-xs text-red-600">{{ fieldErrors.aadhar_back_image }}</p>
          </div>
        </section>

        <!-- Salary, joining date, report password -->
        <section
          v-if="has('salary') || has('joining_date') || has('report_password')"
          class="grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-2 lg:grid-cols-4"
        >
          <template v-if="has('salary')">
            <BaseSelect
              id="user_salary_person"
              v-model="form.is_salary_person"
              label="Salary person"
              :options="salaryOptions"
            />
            <BaseInput
              id="user_per_day_salary"
              v-model="form.per_day_salary"
              label="Per day salary (₹)"
              type="number"
              step="0.01"
              :disabled="!form.is_salary_person"
              :placeholder="form.is_salary_person ? '0.00' : 'Not a salary person'"
              :error="fieldErrors.per_day_salary"
            />
          </template>
          <BaseInput
            v-if="has('joining_date')"
            id="user_joining_date"
            v-model="form.joining_date"
            label="Joining date"
            type="date"
            :error="fieldErrors.joining_date"
          />
          <div v-if="has('report_password')">
            <BaseInput
              v-if="!isEditing || isChangingReportPassword || !hasReportPassword"
              id="user_report_password"
              v-model="form.report_password"
              label="Report password"
              type="password"
              autocomplete="new-password"
              :placeholder="isEditing && hasReportPassword ? 'New report password' : 'Optional'"
              :error="fieldErrors.report_password"
            />
            <template v-else>
              <span class="mb-1 block text-sm font-medium text-slate-700">Report password</span>
              <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                Set
                <button type="button" class="text-xs font-medium text-brand-600 hover:text-brand-700" @click="isChangingReportPassword = true">
                  Change
                </button>
              </div>
            </template>
          </div>
        </section>


        <!-- Account (full form) -->
        <section v-if="has('account')" class="flex flex-wrap gap-6 border-t border-slate-200 pt-5">
          <BaseCheckbox v-model="form.is_billable" label="Billable" />
          <BaseCheckbox v-model="form.is_delete" label="Marked as deleted" />
        </section>

        <!-- Permissions -->
        <section v-if="has('flags') && switchFlags.length" class="border-t border-slate-200 pt-5">
          <div class="mb-3 flex items-baseline justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Permissions</h3>
            <span class="text-xs text-slate-500 tabular-nums">{{ enabledFlagCount }} of {{ switchFlags.length }} on</span>
          </div>
          <div v-if="flatFlags" class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
            <BaseCheckbox v-for="key in switchFlags" :key="key" v-model="form[key]" :label="flagLabel(key)" />
          </div>
          <div v-else class="grid gap-4 lg:grid-cols-2">
            <div v-for="group in flagGroups" :key="group.title" class="rounded-lg border border-slate-200 p-3">
              <div class="mb-2 flex items-center justify-between gap-2">
                <div>
                  <h4 class="text-xs font-semibold tracking-wide text-slate-700 uppercase">{{ group.title }}</h4>
                  <p class="text-xs text-slate-500">{{ group.description }}</p>
                </div>
                <button
                  type="button"
                  class="shrink-0 text-xs font-medium text-brand-600 hover:text-brand-700"
                  @click="toggleGroup(group.flags.map((f) => f.key))"
                >
                  {{ group.flags.every((f) => form[f.key]) ? 'All off' : 'All on' }}
                </button>
              </div>
              <div class="flex flex-col gap-2">
                <BaseCheckbox v-for="flag in group.flags" :key="flag.key" v-model="form[flag.key]" :label="flag.label" />
              </div>
            </div>
          </div>
        </section>

        <!-- Special password, when heads or opening balances go out -->
        <section v-if="needsSpecialPassword" class="border-t border-slate-200 pt-5">
          <div class="max-w-sm">
            <BaseInput
              id="user_special_password"
              v-model="specialPassword"
              label="Special password"
              type="password"
              autocomplete="off"
              :placeholder="sendsHeads ? 'Required to assign heads' : 'Required for the opening balances'"
              required
              :error="fieldErrors.special_password"
            />
            <p class="mt-1 text-xs text-slate-500">
              <template v-if="sendsHeads">
                {{ isEditing ? 'You changed this user’s head assignments.' : 'You assigned heads.' }}
              </template>
              <template v-if="!isEditing && pendingOpenings.length">You entered opening balances.</template>
              That needs your special password.
            </p>
          </div>
        </section>
      </template>

      <p v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ formError }}
      </p>

      <div class="flex items-center gap-3 border-t border-slate-200 pt-5">
        <BaseButton type="submit" :disabled="isSaving || !profile">
          {{ isSaving ? 'Saving…' : isEditing ? 'Update' : 'Create user' }}
        </BaseButton>
        <BaseButton variant="secondary" type="button" :disabled="isSaving" @click="emit('close')">
          Cancel
        </BaseButton>
      </div>
    </form>

    <OpeningBalanceModal
      v-if="openingTarget"
      :title="openingTarget.title"
      :field="openingTarget.field"
      :previous="openingTarget.previous"
      :requires-password="isEditing"
      :submitting="openingSubmitting"
      :error="openingError"
      @save="saveOpening"
      @close="openingTarget = null"
    />
  </BaseCard>
</template>
