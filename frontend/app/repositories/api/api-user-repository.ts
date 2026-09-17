import type { User } from '~/domain/user'
import type { UserRepository } from '~/repositories/contracts/user-repository'
import { createApiRequest } from './request'

export function createApiUserRepository(): UserRepository {
  const api = createApiRequest()

  return {
    list: () => api<User[]>('/users'),
    findById: id => api<User>(`/users/${encodeURIComponent(id)}`),
    create: input => api<User>('/users', { method: 'POST', body: input }),
    update: (id, input) => api<User>(`/users/${encodeURIComponent(id)}`, { method: 'PATCH', body: input }),
    updateOwnProfile: input => api<User>('/users/me', { method: 'PATCH', body: input }),
    deactivate: id => api<User>(`/users/${encodeURIComponent(id)}/deactivate`, { method: 'POST' }),
  }
}
