import type { Unit } from '~/domain/user'
import type { UnitRepository } from '~/repositories/contracts/unit-repository'
import { createApiRequest } from './request'

export function createApiUnitRepository(): UnitRepository {
  const api = createApiRequest()

  return {
    list: () => api<Unit[]>('/units'),
    findById: id => api<Unit>(`/units/${encodeURIComponent(id)}`),
    create: input => api<Unit>('/units', { method: 'POST', body: input }),
    update: (id, input) => api<Unit>(`/units/${encodeURIComponent(id)}`, { method: 'PATCH', body: input }),
    deactivate: id => api<Unit>(`/units/${encodeURIComponent(id)}/deactivate`, { method: 'POST' }),
  }
}

