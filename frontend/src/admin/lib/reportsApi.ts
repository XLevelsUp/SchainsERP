import type { ApiResponse } from '@/lib/api'
import { adminSession } from '@/lib/authSession'
import { createStockReportsApi } from '@/lib/stockReportsApi'
import type { OneDayActionQuery, OneDayActionResult } from '@/types'
import { ADMIN_BASE_URL, adminApi } from './adminApi'

// The stock reports in the admin console (2026-11-24): GET /admin/reports/*
// (Admin\AdminReportController). Items OB & CB and Consolidated need a
// head_id (one section per head on screen); One Day Action covers every
// head unless head_id narrows it; ID Wise opens any stock ID.

export const adminStockReportsApi = createStockReportsApi(adminApi, '/reports', {
  baseUrl: ADMIN_BASE_URL,
  session: adminSession,
})

function buildQuery(params: OneDayActionQuery): string {
  const qs = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') qs.set(key, String(value))
  }
  const s = qs.toString()
  return s ? `?${s}` : ''
}

export const adminOneDayActionApi = {
  get: (params: OneDayActionQuery) =>
    adminApi
      .get<ApiResponse<OneDayActionResult>>(`/reports/one-day-action${buildQuery(params)}`)
      .then((r) => r.data),
}
