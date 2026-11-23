/*
| Users' phone numbers (agreed 2026-11-23): exactly 10 digits, a leading 0
| allowed, and no two users may share one. The backend enforces both
| (UserDetailController: PHONE_PATTERN, unique) on every create and edit;
| these helpers give the same answer before the request is sent. Used by the
| admin console's Users form and the app's Add Retailer.
*/
export const PHONE_LENGTH = 10

export const PHONE_ERROR = `Phone number must be exactly ${PHONE_LENGTH} digits.`

const PHONE_PATTERN = new RegExp(`^\\d{${PHONE_LENGTH}}$`)

export const isValidPhone = (value: string) => PHONE_PATTERN.test(value.trim())

// What the phone field keeps as the user types or pastes: digits only, at
// most 10. A pasted "98765 43210" or "+91-..." keeps just its digits; the
// check above still decides whether the result is a valid number.
export const phoneDigits = (value: string) => value.replace(/\D/g, '').slice(0, PHONE_LENGTH)
