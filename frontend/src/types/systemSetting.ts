// GET|POST /settings, GET|PUT|PATCH|DELETE /settings/{key}
// (SystemSettingController, PR #43–#45). Table `system_settings`.
//
// The route is declared `->parameters(['settings' => 'key'])` and every
// single-row method does `where('setting_key', $key)->firstOrFail()`, so the
// URL segment is the **setting_key string, not the numeric id**. The `id`
// below comes back on reads but is never used to address a row.

// `setting_value` is a json column cast to `array` on the model, and store()
// validates it `required|array`. A bare string or number is rejected with a
// 422 — the value must be a JSON object or array. Modelled as `unknown`
// rather than a concrete shape because each key defines its own payload and
// nothing on the backend constrains it.
export interface SystemSetting {
  id: number
  setting_key: string
  setting_value: unknown
  description: string | null
  created_at: string
  updated_at: string
}

// POST body. setting_key must be unique — a clash is a 422 on
// `unique:system_settings,setting_key`.
export interface SystemSettingCreate {
  setting_key: string
  setting_value: unknown
  description?: string | null
}

// PUT body. Both fields are optional server-side (`sometimes|required|array`
// and `nullable|string`) and only the keys sent are written.
//
// `setting_key` is deliberately absent: update() validates only these two and
// writes `$validated`, so **a key cannot be renamed through the API**. The UI
// shows it read-only on edit; renaming means delete + recreate.
export interface SystemSettingUpdate {
  setting_value?: unknown
  description?: string | null
}
