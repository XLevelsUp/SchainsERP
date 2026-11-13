import { api, type ApiResponse } from './api'
import type { GoldCorrectionResult, GoldEntryDetail } from '@/types'

const RESOURCE = '/gold-entries'

// Gold entry corrections (heads only, BACKEND_TODO §17 phase 3). The four
// gold modals submit through their own API module's `correct()`, which
// builds the same fields as a new entry and posts them here.
export const goldEntriesApi = {
  get: (goldEntryId: number) =>
    api.get<ApiResponse<GoldEntryDetail>>(`${RESOURCE}/${goldEntryId}`).then((r) => r.data),

  correct: (goldEntryId: number, fields: Record<string, unknown>) =>
    api
      .post<ApiResponse<GoldCorrectionResult>>(`${RESOURCE}/${goldEntryId}/correct`, fields)
      .then((r) => r.data),
}
