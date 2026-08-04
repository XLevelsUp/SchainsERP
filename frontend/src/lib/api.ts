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

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  let response: Response
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...options.headers,
      },
      ...options,
    })
  } catch {
    throw new ApiError('Could not reach the server. Is the backend running?', 0)
  }

  // 204 No Content or empty body
  const text = await response.text()
  const body = text ? JSON.parse(text) : null

  if (!response.ok) {
    const message =
      (body && (body.message || body.error)) || `Request failed (${response.status})`
    throw new ApiError(message, response.status, body?.errors)
  }

  return body as T
}

export const api = {
  get: <T>(path: string) => request<T>(path, { method: 'GET' }),
  post: <T>(path: string, data: unknown) =>
    request<T>(path, { method: 'POST', body: JSON.stringify(data) }),
  put: <T>(path: string, data: unknown) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(data) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}