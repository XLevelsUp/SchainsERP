import { appSession, type AuthSessionStore } from './authSession'

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

// In dev the SPA and the API share an origin: Vite proxies `/api` to the
// Laravel server (see vite.config.ts), so a relative base is correct and
// needs no configuration. Deployed they are separate hosts — the SPA on
// Vercel, Laravel on Render — so VITE_API_BASE_URL supplies the absolute
// origin, e.g. https://api.lensnstories.com/api/v1. Trailing slashes are
// trimmed so a value with or without one builds the same URL.
//
// Exported because lib/download.ts builds the same URLs without going through
// request(): the report export endpoints stream a CSV body, which request()
// would try to parse as JSON. One definition, two consumers.
export const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? '/api/v1').replace(/\/+$/, '')

interface ErrorBody {
  message?: string
  error?: string
  errors?: Record<string, string[]>
}

function asErrorBody(body: unknown): ErrorBody {
  return typeof body === 'object' && body !== null ? (body as ErrorBody) : {}
}

// What a client is bound to: where its API lives and whose token it sends.
// The app and the admin console each have one (see createApiClient below).
interface ApiClientConfig {
  baseUrl: string
  session: AuthSessionStore<unknown>
  // Sent on every request this client makes, e.g. the admin console's
  // X-Special-Password for one sensitive change (admin/lib/adminApi.ts).
  headers?: Record<string, string>
}

async function request<T>(
  config: ApiClientConfig,
  path: string,
  options: RequestInit = {},
): Promise<T> {
  // Let the browser set the multipart boundary itself for FormData bodies.
  const isFormData = options.body instanceof FormData
  // Read once: the same value decides whether a 401 means "expired session"
  // or "bad credentials" further down.
  const token = config.session.getToken()

  let response: Response
  try {
    response = await fetch(`${config.baseUrl}${path}`, {
      ...options,
      headers: {
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        // Laravel needs this to answer unauthenticated requests with a 401
        // JSON body instead of redirecting to a web login page.
        Accept: 'application/json',
        // Every route but POST /login sits behind `auth:api` since PR #32.
        // Listed before ...options.headers so a caller can still override it.
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...config.headers,
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
      config.session.clear()
      config.session.notifyUnauthorized()
      throw new ApiError('Your session has expired. Please sign in again.', 401)
    }

    const parsed = asErrorBody(body)
    const message = parsed.message || parsed.error || `Request failed (${response.status})`
    throw new ApiError(message, response.status, parsed.errors)
  }

  return body as T
}

export interface ApiClient {
  get<T>(path: string, headers?: Record<string, string>): Promise<T>
  post<T>(path: string, data: unknown, headers?: Record<string, string>): Promise<T>
  put<T>(path: string, data: unknown): Promise<T>
  delete<T>(path: string): Promise<T>
  postForm<T>(path: string, data: FormData, headers?: Record<string, string>): Promise<T>
}

// Resource modules (itemsApi, rolesApi, ...) are written against ApiClient,
// so the same request code serves both realms: `api` below for the app, and
// the admin console's client (admin/lib/adminApi.ts), which points at
// /api/v1/admin and sends the admin session's token.
export function createApiClient(config: ApiClientConfig): ApiClient {
  return {
    get: <T>(path: string, headers?: Record<string, string>) =>
      request<T>(config, path, { method: 'GET', headers }),
    post: <T>(path: string, data: unknown, headers?: Record<string, string>) =>
      request<T>(config, path, { method: 'POST', body: JSON.stringify(data), headers }),
    put: <T>(path: string, data: unknown) =>
      request<T>(config, path, { method: 'PUT', body: JSON.stringify(data) }),
    delete: <T>(path: string) => request<T>(config, path, { method: 'DELETE' }),
    postForm: <T>(path: string, data: FormData, headers?: Record<string, string>) =>
      request<T>(config, path, { method: 'POST', body: data, headers }),
  }
}

// The operational app's client: /api/v1 with the app session's token.
export const api = createApiClient({ baseUrl: API_BASE_URL, session: appSession })
