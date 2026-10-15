import type { ApiResponse } from '~/domain/common'
import { rolePermissions, type UseCase } from '~/domain/permissions'
import type { PermissionCatalogItem, RolePermissionSummary, UserPermissionSummary } from '~/domain/permission'
import type { PermissionRepository } from '~/repositories/contracts/permission-repository'
import { mockUsers } from './mock-user-repository'
import { simulateRequest } from './mock-config'

const response = <T>(data: T): ApiResponse<T> => ({ data: structuredClone(data), message: null })
const catalog: PermissionCatalogItem[] = [...new Set(Object.values(rolePermissions).flatMap(items => [...items]))].sort().map(name => ({ name }))
const direct = new Map<string, UseCase[]>()

function summary(userId: string): UserPermissionSummary {
  const user = mockUsers.find(item => item.id === userId)
  if (!user) throw new Error('Usuário não encontrado')
  const fromRole = [...rolePermissions[user.role]]
  const userDirect = direct.get(userId) ?? []
  return { userId, role: user.role, roleLabel: user.role, fromRole, direct: userDirect, effective: [...new Set([...fromRole, ...userDirect])] }
}

export const mockPermissionRepository: PermissionRepository = {
  async list() { await simulateRequest(); return response(catalog) },
  async roles() { await simulateRequest(); return response(Object.entries(rolePermissions).map(([name, permissions]) => ({ name, permissions: [...permissions] }))) },
  async forUser(userId) { await simulateRequest(); return response(summary(userId)) },
  async grant(userId, permission) { await simulateRequest(); const current = direct.get(userId) ?? []; if (!current.includes(permission as UseCase)) direct.set(userId, [...current, permission as UseCase]); return response({ userId, direct: direct.get(userId) ?? [] }) },
  async revoke(userId, permission) { await simulateRequest(); direct.set(userId, (direct.get(userId) ?? []).filter(item => item !== permission)); return response({ userId, direct: direct.get(userId) ?? [] }) },
}

