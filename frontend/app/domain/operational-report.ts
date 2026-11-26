export interface ReportRows<T> { data: T[]; total: number; page: number; per_page: number; last_page: number }
export interface ReportPeriod { from: string; to: string }
export interface ReportFilters extends ReportPeriod { page?: number; limit?: number; export?: boolean; status?: string; kind?: string; occurrence_type_id?: string; common_area_id?: string }
export interface OccurrenceReportRow { id: number; title: string; status: string; created_at: string; occurrence_type?: { name: string; kind?: string } | null; resolved?: boolean | null }
export interface ReservationReportRow { id: number; date: string; start_time: string | null; end_time: string | null; status: string; common_area?: { name: string } | null; resident?: { name: string; unit?: { code: string } | null } | null }
export interface OccurrenceReport {
  period: ReportPeriod
  summary: { total: number; open: number; completed: number; cancelled: number; resolved: number; unresolved: number; average_resolution_hours: number | null }
  by_status: Array<{ status: string; label: string; total: number }>
  by_type: Array<{ occurrence_type_id: number; name: string; total: number }>
  by_kind: Array<{ kind: string; label: string; total: number }>
  occurrences: ReportRows<OccurrenceReportRow>
}
export interface ReservationReport {
  period: ReportPeriod
  summary: { total: number; pending: number; approved: number; rejected: number; cancelled: number }
  by_status: Array<{ status: string; label: string; total: number }>
  by_area: Array<{ common_area_id: number; name: string; total: number; pending: number; approved: number; rejected: number; cancelled: number }>
  reservations: ReportRows<ReservationReportRow>
}
