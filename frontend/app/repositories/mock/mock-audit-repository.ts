import type { AuditLog } from '~/domain/audit'
import type { ApiResponse } from '~/domain/common'
import type { AuditRepository } from '~/repositories/contracts/audit-repository'
import { simulateRequest } from './mock-config'

export const mockAuditLogs: AuditLog[] = [
  { id: 'audit-01', userId: 'user-admin', action: 'occurrence.assigned', entity: 'occurrence', entityId: 'occurrence-01', createdAt: '2026-09-15T09:10:00Z', metadata: { employeeId: 'user-employee' } },
  { id: 'audit-02', userId: 'user-employee', action: 'occurrence.status_updated', entity: 'occurrence', entityId: 'occurrence-01', createdAt: '2026-09-16T10:00:00Z', metadata: { from: 'assigned', to: 'in_progress' } },
  { id: 'audit-03', userId: 'user-admin', action: 'reservation.approved', entity: 'reservation', entityId: 'reservation-01', createdAt: '2026-09-03T14:20:00Z' },
  { id: 'audit-04', userId: 'user-admin', action: 'user.deactivated', entity: 'user', entityId: 'user-former', createdAt: '2026-09-02T16:45:00Z', metadata: { reason: 'Encerramento de vínculo' } },
  { id: 'audit-05', userId: 'user-syndic', action: 'notice.published', entity: 'notice', entityId: 'notice-01', createdAt: '2026-09-10T10:00:00Z' },
]

export function recordAudit(entry: Omit<AuditLog, 'id' | 'createdAt'> & { createdAt?: string }) {
  mockAuditLogs.unshift({ ...entry, id: crypto.randomUUID(), createdAt: entry.createdAt ?? new Date().toISOString() })
}

export const mockAuditRepository: AuditRepository = {
  async list(): Promise<ApiResponse<AuditLog[]>> {
    await simulateRequest()
    return { data: structuredClone(mockAuditLogs), message: null }
  },
}
