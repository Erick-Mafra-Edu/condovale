import type { ApiResponse } from '~/domain/common'
import type { AuditFilters, AuditReport } from '~/domain/audit-report'

export interface AuditReportRepository { get(filters: AuditFilters): Promise<ApiResponse<AuditReport>> }
