import type { ApiResponse } from '~/domain/common'
import type { PermissionCatalogItem, RolePermissionSummary, UserPermissionSummary } from '~/domain/permission'
import type { PermissionRepository } from '~/repositories/contracts/permission-repository'
import { createApiRequest } from './request'

type ApiUserPermissionSummary = Omit<UserPermissionSummary, 'userId' | 'roleLabel' | 'fromRole'> & {
  user_id: string | number
  role_label: string
  from_role: UserPermissionSummary['fromRole']
}

function mapSummary(value: ApiUserPermissionSummary): UserPermissionSummary {
  return {
    userId: String(value.user_id),
    role: value.role,
    roleLabel: value.role_label,
    fromRole: value.from_role,
    direct: value.direct,
    effective: value.effective,
  }
}

export function createApiPermissionRepository(): PermissionRepository {
  const api = createApiRequest()
  return {
    async list() {
      return api<PermissionCatalogItem[]>('/permissions', { query: { limit: 100 } })
    },
    roles() {
      return api<RolePermissionSummary[]>('/roles')
    },
    async forUser(userId) {
      const response = await api<ApiUserPermissionSummary>(`/users/${encodeURIComponent(userId)}/permissions`)
      return { ...response, data: mapSummary(response.data) }
    },
    grant: async (userId, permission) => {
      const response = await api<{ user_id: string | number; direct: UserPermissionSummary['direct'] }>(`/users/${encodeURIComponent(userId)}/permissions`, { method: 'POST', body: { permission } })
      return { ...response, data: { userId: String(response.data.user_id), direct: response.data.direct } }
    },
    revoke: async (userId, permission) => {
      const response = await api<{ user_id: string | number; direct: UserPermissionSummary['direct'] }>(`/users/${encodeURIComponent(userId)}/permissions/${encodeURIComponent(permission)}`, { method: 'DELETE' })
      return { ...response, data: { userId: String(response.data.user_id), direct: response.data.direct } }
    },
  }
}

