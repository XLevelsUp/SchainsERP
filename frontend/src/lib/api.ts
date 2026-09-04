import { clearSession, getToken, notifyUnauthorized } from './authSession'

// Shared API envelope returned by the Laravel backend: { success, message, data }
export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export class ApiError extends Error {
  status: number
  // Laravel validation error bag: { field: [message, ...] }
  errors?: Record<string, string[]>
  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

const BASE_URL = '/api/v1'

interface ErrorBody {
  message?: string
  error?: string
  errors?: Record<string, string[]>
}

function asErrorBody(body: unknown): ErrorBody {
  return typeof body === 'object' && body !== null ? (body as ErrorBody) : {}
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  // Let the browser set the multipart boundary itself for FormData bodies.
  const isFormData = options.body instanceof FormData
  // Read once: the same value decides whether a 401 means "expired session"
  // or "bad credentials" further down.
  const token = getToken()

  let response: Response
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      ...options,
      headers: {
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        // Laravel needs this to answer unauthenticated requests with a 401
        // JSON body instead of redirecting to a web login page.
        Accept: 'application/json',
        // Every route but POST /login sits behind `auth:api` since PR #32.
        // Listed before ...options.headers so a caller can still override it.
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...options.headers,
      },
    })
  } catch {
    throw new ApiError('Could not reach the server. Is the backend running?', 0)
  }

  // 204 No Content or empty body
  const text = await response.text()
  let body: unknown = null
  if (text) {
    try {
      body = JSON.parse(text)
    } catch {
      // A non-JSON response (proxy error page, PHP fatal) should surface as
      // an ApiError with the status, not a SyntaxError at the call site.
      body = null
    }
  }

  if (!response.ok) {
    // Passport rejected the bearer token — expired, revoked by a logout
    // elsewhere, or the server's keys changed. Drop the dead session and let
    // the registered handler move the operator to the login screen.
    //
    // Gated on `token` on purpose: a 401 from POST /login is a wrong
    // username/password and must reach the form as a normal error instead.
    if (response.status === 401 && token) {
      clearSession()
      notifyUnauthorized()
      throw new ApiError('Your session has expired. Please sign in again.', 401)
    }

    const parsed = asErrorBody(body)
    const message = parsed.message || parsed.error || `Request failed (${response.status})`
    throw new ApiError(message, response.status, parsed.errors)
  }

  return body as T
}

export const api = {
  get: <T>(path: string) => request<T>(path, { method: 'GET' }),
  post: <T>(path: string, data: unknown, headers?: Record<string, string>) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(data), headers }),
  put: <T>(path: string, data: unknown) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(data) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
  postForm: <T>(path: string, data: FormData, headers?: Record<string, string>) =>
    request<T>(path, { method: 'POST', body: data, headers }),
}
