import { API_BASE_URL, ApiError } from './api'
import { clearSession, getToken, notifyUnauthorized } from './authSession'

/*
|--------------------------------------------------------------------------
| Authenticated file downloads
|--------------------------------------------------------------------------
| Deliberately separate from lib/api.ts rather than another method on `api`.
| request() reads the body as text and parses it as JSON; these endpoints
| return a streamed CSV, so routing them through it would either corrupt the
| payload or throw on a perfectly good response.
|
| Why not a plain <a href> or window.open: every export route sits behind
| `auth:api`, and a browser navigation cannot carry the Authorization
| header. It would arrive unauthenticated and come back 401. So the file has
| to be fetched with the header, turned into a blob, and handed to a
| temporary object-URL anchor.
|
| The 401 branch mirrors request() exactly — drop the dead session and fire
| the same handler — so an export that outlives the token bounces the
| operator to the login screen like any other call, instead of silently
| saving an error page as a .csv.
|--------------------------------------------------------------------------
*/

// Content-Disposition wins when the server sends one: the two report
// endpoints name their own files (history_items_obcb.csv, etc.) and that
// name should be what lands in the operator's downloads folder.
function filenameFrom(header: string | null, fallback: string): string {
  if (!header) return fallback
  // Handles both `filename="x.csv"` and RFC 5987 `filename*=UTF-8''x.csv`.
  const star = /filename\*=(?:UTF-8'')?"?([^";]+)"?/i.exec(header)
  if (star?.[1]) return decodeURIComponent(star[1])
  const plain = /filename="?([^";]+)"?/i.exec(header)
  return plain?.[1] ?? fallback
}

// A failed download still returns JSON (the controllers catch \Throwable and
// return a 500 envelope), so parse it the way request() would to get a real
// message rather than "Request failed".
async function errorFrom(response: Response): Promise<ApiError> {
  let message = `Download failed (${response.status})`
  try {
    const text = await response.text()
    if (text) {
      const body = JSON.parse(text) as { message?: string; error?: string }
      message = body.message || body.error || message
    }
  } catch {
    // Non-JSON error body (proxy page, PHP fatal) — keep the status message.
  }
  return new ApiError(message, response.status)
}

/**
 * GETs `path` with the bearer token and saves the response as a file.
 *
 * @param path     API path below the v1 base, e.g. `/stock/reports/items-obcb/export`.
 * @param fallback Filename to use when the response carries no Content-Disposition.
 */
export async function downloadFile(path: string, fallback: string): Promise<void> {
  const token = getToken()

  let response: Response
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method: 'GET',
      headers: {
        // Not application/json: this response is a CSV stream. Laravel still
        // needs to know it must not redirect to a web login page, which the
        // Authorization header below already settles.
        Accept: 'text/csv, application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    })
  } catch {
    throw new ApiError('Could not reach the server. Is the backend running?', 0)
  }

  if (response.status === 401 && token) {
    clearSession()
    notifyUnauthorized()
    throw new ApiError('Your session has expired. Please sign in again.', 401)
  }

  if (!response.ok) throw await errorFrom(response)

  const blob = await response.blob()
  const filename = filenameFrom(response.headers.get('Content-Disposition'), fallback)

  const url = URL.createObjectURL(blob)
  try {
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    // Firefox only fires the download for an anchor that is in the document.
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
  } finally {
    // Revoking synchronously is safe — the click has already handed the blob
    // to the browser's download manager.
    URL.revokeObjectURL(url)
  }
}
