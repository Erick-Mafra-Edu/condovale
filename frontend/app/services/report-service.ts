import type { ReportRepository } from '~/repositories/contracts/report-repository'
import type { ReportFilters } from '~/domain/operational-report'
export function createReportService(repository: ReportRepository) { return { occurrences: (filters: ReportFilters) => repository.occurrences(filters), reservations: (filters: ReportFilters) => repository.reservations(filters) } }
