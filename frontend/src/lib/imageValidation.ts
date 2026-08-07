// Mirrors CashTxnDetailController::addImages()'s validator: image,
// mimes:jpg,jpeg,png,webp, max:5120 (KB). Catching this client-side avoids a
// generic 422 after the fact — and since the backend validates the whole
// batch before storing anything, one bad file would otherwise reject every
// good file selected alongside it.
export const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp']
export const MAX_IMAGE_SIZE_BYTES = 5120 * 1024

// Returns an error message if the file should be rejected, or null if it's fine.
export function validateImageFile(file: File): string | null {
  if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
    return `${file.name}: unsupported file type. Use JPG, PNG or WEBP.`
  }
  if (file.size > MAX_IMAGE_SIZE_BYTES) {
    return `${file.name}: file is larger than 5MB.`
  }
  return null
}
