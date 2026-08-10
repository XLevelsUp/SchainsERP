// Builds a multipart FormData body for the gold-conversion endpoints
// (cash-to-gold, gold-to-cash, sale-gold, purchase-gold), which all accept
// an optional `images[]` receipt upload (PR #15) alongside their normal
// fields. Laravel reads nested arrays/objects from bracket-notation keys
// (amount_sources[0][source], images[]), so plain JSON isn't an option once
// a File needs to go in the same request.
export function buildGoldConversionForm(fields: Record<string, unknown>, images: File[]): FormData {
  const formData = new FormData()

  function append(key: string, value: unknown) {
    if (value === null || value === undefined) return
    if (Array.isArray(value)) {
      value.forEach((item, index) => append(`${key}[${index}]`, item))
    } else if (typeof value === 'object') {
      Object.entries(value as Record<string, unknown>).forEach(([k, v]) => append(`${key}[${k}]`, v))
    } else if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0')
    } else {
      formData.append(key, String(value))
    }
  }

  Object.entries(fields).forEach(([key, value]) => append(key, value))
  images.forEach((file) => formData.append('images[]', file))

  return formData
}
