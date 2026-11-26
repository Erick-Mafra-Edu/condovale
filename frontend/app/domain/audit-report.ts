import type { ReportPeriod, ReportRows } from './operational-report'

export interface AuditReportRow {
  id: number; action: string; action_label: string | null; entity: string | null; entity_label: string | null
  entity_id: number | null; description: string; user: { id: number; name: string } | null
  user_role: string | null; user_role_label: string | null; metadata: Record<string, unknown> | null; created_at: string
}
export interface AuditReport {
  period: ReportPeriod
  summary: { total: number; admin?: number; employee?: number; syndic?: number; resident?: number }
  by_role: Array<{ role: string; label: string; total: number }>
  by_entity: Array<{ entity: string; label: string; total: number }>
  logs: ReportRows<AuditReportRow>
}
export interface AuditFilters extends ReportPeriod { page?: number; limit?: number; export?: boolean; entity?: string; entity_id?: string; user_id?: string; role?: string; action?: string }
