import type { ApiResponse } from '~/domain/common'
import type { OccurrenceReport, ReportFilters, ReservationReport } from '~/domain/operational-report'

export interface ReportRepository {
  occurrences(filters: ReportFilters): Promise<ApiResponse<OccurrenceReport>>
  reservations(filters: ReportFilters): Promise<ApiResponse<ReservationReport>>
}
