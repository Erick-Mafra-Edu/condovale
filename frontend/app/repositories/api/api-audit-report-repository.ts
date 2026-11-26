import type { AuditReportRepository } from '~/repositories/contracts/audit-report-repository'
import { createApiRequest } from './request'

export function createApiAuditReportRepository(): AuditReportRepository {
  const request = createApiRequest()
  return { get: filters => request('/audit-logs', { query: filters }) }
}
