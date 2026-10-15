import type { PermissionRepository } from '~/repositories/contracts/permission-repository'

export function createPermissionService(repository: PermissionRepository) {
  return {
    list: () => repository.list(),
    roles: () => repository.roles(),
    forUser: (userId: string) => repository.forUser(userId),
    grant: (userId: string, permission: string) => repository.grant(userId, permission),
    revoke: (userId: string, permission: string) => repository.revoke(userId, permission),
  }
}

