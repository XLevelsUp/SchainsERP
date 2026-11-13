import { api, type ApiResponse } from './api'
import type {
  NumericWastageCorrectionPayload,
  NumericWastageCorrectionResult,
  NumericWastageDirection,
  NumericWastageEntryDetail,
} from '@/types'

const RESOURCE = '/numeric-wastage-entries'

// Numeric wastage line corrections (heads only, BACKEND_TODO §17). {id} is
// the line's stock row id.
export const numericWastageEntriesApi = {
  get: (direction: NumericWastageDirection, id: number) =>
    api.get<ApiResponse<NumericWastageEntryDetail>>(`${RESOURCE}/${direction}/${id}`).then((r) => r.data),

  correct: (direction: NumericWastageDirection, id: number, payload: NumericWastageCorrectionPayload) =>
    api
      .post<ApiResponse<NumericWastageCorrectionResult>>(`${RESOURCE}/${direction}/${id}/correct`, payload)
      .then((r) => r.data),
}
