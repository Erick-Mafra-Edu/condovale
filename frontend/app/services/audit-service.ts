import type { AuditRepository } from '~/repositories/contracts/audit-repository'

export function createAuditService(repository: AuditRepository) {
  return { list: () => repository.list() }
}
