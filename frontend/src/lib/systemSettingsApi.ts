import { api, type ApiResponse } from './api'
import type { SystemSetting, SystemSettingCreate, SystemSettingUpdate } from '@/types'

const RESOURCE = '/settings'

// Rows are addressed by `setting_key`, not by id — the route is registered
// with ->parameters(['settings' => 'key']) and the controller looks every
// row up with where('setting_key', $key). Keys can contain characters that
// need escaping in a path segment, so encode them.
const keyPath = (key: string) => `${RESOURCE}/${encodeURIComponent(key)}`

export const systemSettingsApi = {
  list: () => api.get<ApiResponse<SystemSetting[]>>(RESOURCE).then((r) => r.data),

  get: (key: string) => api.get<ApiResponse<SystemSetting>>(keyPath(key)).then((r) => r.data),

  // 201. `setting_key` must be unique and `setting_value` must be a JSON
  // object or array — a scalar fails validation with a 422.
  create: (payload: SystemSettingCreate) =>
    api.post<ApiResponse<SystemSetting>>(RESOURCE, payload).then((r) => r.data),

  // Partial; `setting_key` is not updatable (see SystemSettingUpdate).
  update: (key: string, payload: SystemSettingUpdate) =>
    api.put<ApiResponse<SystemSetting>>(keyPath(key), payload).then((r) => r.data),

  remove: (key: string) => api.delete<ApiResponse<null>>(keyPath(key)).then((r) => r.data),
}
