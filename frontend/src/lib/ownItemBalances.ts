import { onMounted, ref } from 'vue'
import { ApiError } from './api'
import { headStocksApi } from './headStocksApi'
import { useAuthStore } from '@/stores/auth'

/*
|--------------------------------------------------------------------------
| The logged-in user's available grams per item, for item dropdowns
|--------------------------------------------------------------------------
| The legacy Item Change dropdowns read "GOLD => 0", "STONE => 23.138":
| each item with the grams you hold of it. Item Change and Item Conversion
| only ever move the logged-in user's own stock, so the figure is that
| user's live item total — the same GET /stock-details/head-stocks the
| Head Stocks panel shows (users_items_mapping.item_grams_total, no as-of
| filter), so the dropdown and the panel can never disagree.
|
| An item with no mapping row for the user has no balance and reads 0.000,
| as in the legacy screen. If the request fails, hints are left off rather
| than showing zeros that would look like real balances.
|--------------------------------------------------------------------------
*/

function formatGrams(value: number) {
  return value.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 })
}

export function useOwnItemBalances() {
  const auth = useAuthStore()
  const gramsByItem = ref<Map<number, number> | null>(null)
  const loadError = ref('')

  async function load() {
    const userId = auth.user?.user_id
    if (userId == null) return
    loadError.value = ''
    try {
      const summary = await headStocksApi.get(userId)
      gramsByItem.value = new Map(summary.items.map((i) => [Number(i.item_id), Number(i.grams) || 0]))
    } catch (err) {
      gramsByItem.value = null
      loadError.value = err instanceof ApiError ? err.message : 'Failed to load your item balances.'
    }
  }

  onMounted(load)

  // "23.138 g" for BaseSelect's option hint; undefined until loaded.
  function balanceHint(itemId: number): string | undefined {
    const map = gramsByItem.value
    if (!map) return undefined
    return `${formatGrams(map.get(itemId) ?? 0)} g`
  }

  return { load, loadError, balanceHint }
}
