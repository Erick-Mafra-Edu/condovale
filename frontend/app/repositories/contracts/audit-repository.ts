import type { AuditLog } from '~/domain/audit'
import type { ApiResponse } from '~/domain/common'

export interface AuditRepository {
  list(): Promise<ApiResponse<AuditLog[]>>
}
