import type { User, UserCreationResult } from '~/domain/user'
import type { UserRepository } from '~/repositories/contracts/user-repository'
import { createApiRequest } from './request'
import type { ApiResponse } from '~/domain/common'

export function toApiUser(input: Partial<User>) {
  const { unitId, avatarUrl, mustChangePassword, ...rest } = input
  return { ...rest, ...(unitId !== undefined ? { unit_id: unitId } : {}), ...(avatarUrl !== undefined ? { avatar_url: avatarUrl } : {}) }
}

export function fromApiUser(value: Omit<User, 'id'> & { id: string | number; unit_id?: string | number | null; avatar_url?: string; must_change_password?: boolean }): User {
  const { unit_id, avatar_url, must_change_password, ...user } = value
  return { ...user, id: String(user.id), unitId: unit_id != null ? String(unit_id) : value.unitId ?? undefined, avatarUrl: avatar_url ?? value.avatarUrl, mustChangePassword: must_change_password ?? value.mustChangePassword }
}

type ApiUserListResponse = ApiResponse<Array<User & { id: string | number; unit_id?: string | number | null; avatar_url?: string }>> & { total?: number; amount?: number }

function mapUsers(response: ApiResponse<User[]>): ApiResponse<User[]> { return { ...response, data: response.data.map(item => fromApiUser(item as User & { unit_id?: string; avatar_url?: string })) } }
function mapUser(response: ApiResponse<User>): ApiResponse<User> { return { ...response, data: fromApiUser(response.data as User & { unit_id?: string; avatar_url?: string }) } }

export function createApiUserRepository(): UserRepository {
  const api = createApiRequest()

  return {
    list: async () => {
      const first = await api<User[]>('/users') as ApiUserListResponse
      const total = first.total ?? first.data.length
      const pageSize = first.amount || first.data.length || 10
      const pages = Math.ceil(total / pageSize)
      if (pages <= 1) return mapUsers(first)
      const rest = await Promise.all(Array.from({ length: pages - 1 }, (_, index) => api<User[]>('/users', { query: { page: index + 2 } })) as Promise<ApiUserListResponse>[])
      return mapUsers({ ...first, data: [first, ...rest].flatMap(page => page.data) })
    },
    findById: async id => mapUser(await api<User>(`/users/${encodeURIComponent(id)}`)),
    create: async input => {
      const response = await api<{ user: User & { id: string | number; unit_id?: string | number | null; avatar_url?: string }; temporary_password: string }>('/users', { method: 'POST', body: toApiUser(input) })
      return { ...response, data: { user: fromApiUser(response.data.user), temporaryPassword: response.data.temporary_password } }
    },
    update: async (id, input) => mapUser(await api<User>(`/users/${encodeURIComponent(id)}`, { method: 'PATCH', body: toApiUser(input) })),
    updateOwnProfile: async input => mapUser(await api<User>('/users/me', { method: 'PATCH', body: toApiUser(input) })),
    deactivate: async id => mapUser(await api<User>(`/users/${encodeURIComponent(id)}/deactivate`, { method: 'POST' })),
  }
}
