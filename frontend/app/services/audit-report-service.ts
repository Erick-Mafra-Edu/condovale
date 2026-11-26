import type { AuditReportRepository } from '~/repositories/contracts/audit-report-repository'
import type { AuditFilters } from '~/domain/audit-report'
export function createAuditReportService(repository: AuditReportRepository) { return { get: (filters: AuditFilters) => repository.get(filters) } }
