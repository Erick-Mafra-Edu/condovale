import type { User } from '~/domain/user'
import type { UserRepository } from '~/repositories/contracts/user-repository'
import { createApiRequest } from './request'
import type { ApiResponse } from '~/domain/common'

export function toApiUser(input: Partial<User>) {
  const { unitId, avatarUrl, ...rest } = input
  return { ...rest, ...(unitId !== undefined ? { unit_id: unitId } : {}), ...(avatarUrl !== undefined ? { avatar_url: avatarUrl } : {}) }
}

export function fromApiUser(value: User & { unit_id?: string; avatar_url?: string }): User {
  const { unit_id, avatar_url, ...user } = value
  return { ...user, unitId: unit_id ?? value.unitId, avatarUrl: avatar_url ?? value.avatarUrl }
}

function mapUsers(response: ApiResponse<User[]>): ApiResponse<User[]> { return { ...response, data: response.data.map(item => fromApiUser(item as User & { unit_id?: string; avatar_url?: string })) } }
function mapUser(response: ApiResponse<User>): ApiResponse<User> { return { ...response, data: fromApiUser(response.data as User & { unit_id?: string; avatar_url?: string }) } }

export function createApiUserRepository(): UserRepository {
  const api = createApiRequest()

  return {
    list: async () => mapUsers(await api<User[]>('/users')),
    findById: async id => mapUser(await api<User>(`/users/${encodeURIComponent(id)}`)),
    create: async input => mapUser(await api<User>('/users', { method: 'POST', body: toApiUser(input) })),
    update: async (id, input) => mapUser(await api<User>(`/users/${encodeURIComponent(id)}`, { method: 'PATCH', body: toApiUser(input) })),
    updateOwnProfile: async input => mapUser(await api<User>('/users/me', { method: 'PATCH', body: toApiUser(input) })),
    deactivate: async id => mapUser(await api<User>(`/users/${encodeURIComponent(id)}/deactivate`, { method: 'POST' })),
  }
}
