import type { ReportRepository } from '~/repositories/contracts/report-repository'
import { createApiRequest } from './request'

export function createApiReportRepository(): ReportRepository {
  const request = createApiRequest()
  return {
    occurrences: filters => request('/reports/occurrences', { query: filters }),
    reservations: filters => request('/reports/reservations', { query: filters }),
  }
}
