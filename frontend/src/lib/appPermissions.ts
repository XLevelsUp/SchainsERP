import type { AppPermission } from '@/types'

/*
|--------------------------------------------------------------------------
| What the app shows a signed-in user (agreed 2026-11-21)
|--------------------------------------------------------------------------
| An admin switches features on per user in the admin console's user form
| (the HEAD form's permissions). The app reads them from the session
| (stores/auth.ts → can()) and shows only what's on. Everything starts
| from what's saved: a switch that's off hides its feature.
|
| Where each one is checked:
|   is_item_change_need_shown     Stock — Item Change          (+ API)
|   is_item_conversion_need_shown Stock — Item Conversion      (+ API)
|   is_cash_mngmt_need_to_shown   Stock — Cash Out / Cash In   (+ API, Cash Out)
|   is_phone_book_need_shown      Phone Book (nav and route)   (+ API)
|   is_create_order_shown         Orders — New order           (+ API)
|   is_customer_touch_need_shown  Stock — customer touch panel
|
| "+ API" means the backend refuses the feature's endpoint too
| (EnsureUserPermission). The other HEAD-form switches (delivery, polish,
| WhatsApp share, thermal print, complete history, cash reminder) are stored
| for features the app doesn't have yet.
|--------------------------------------------------------------------------
*/
export const APP_PERMISSIONS: readonly AppPermission[] = [
  'is_item_change_need_shown',
  'is_item_conversion_need_shown',
  'is_cash_mngmt_need_to_shown',
  'is_phone_book_need_shown',
  'is_create_order_shown',
  'is_customer_touch_need_shown',
]
