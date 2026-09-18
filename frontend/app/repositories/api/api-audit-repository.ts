import type { AuditLog } from '~/domain/audit'
import type { AuditRepository } from '~/repositories/contracts/audit-repository'
import { createApiRequest } from './request'

export function createApiAuditRepository(): AuditRepository {
  const api = createApiRequest()
  return { list: () => api<AuditLog[]>('/audit-logs') }
}
