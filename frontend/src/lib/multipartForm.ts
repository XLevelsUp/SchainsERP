// Builds a multipart FormData body for endpoints that accept file uploads
// alongside normal fields (the four gold-conversion endpoints, Cash In/Out,
// and Cash Auto Entry's per-row images). Laravel reads nested arrays/
// objects from bracket-notation keys (amount_sources[0][source],
// transactions[0][images][0]), so plain JSON isn't an option once a File
// needs to go in the same request — and File must be special-cased before
// the generic object branch, or this walker would try to enumerate its
// properties instead of attaching it.
export function buildMultipartForm(fields: Record<string, unknown>): FormData {
  const formData = new FormData()

  function append(key: string, value: unknown) {
    if (value === null || value === undefined) return
    if (value instanceof File) {
      formData.append(key, value)
    } else if (Array.isArray(value)) {
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

  return formData
}

// Thin wrapper for the four gold-conversion endpoints' shape: normal
// fields plus one flat `images[]` receipt set.
export function buildGoldConversionForm(fields: Record<string, unknown>, images: File[]): FormData {
  return buildMultipartForm({ ...fields, images })
}
