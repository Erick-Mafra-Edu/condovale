import type { CreateUnitOccupancyInput, UnitOccupancy } from '~/domain/unit-occupancy'
import type { UnitOccupancyRepository } from '~/repositories/contracts/unit-occupancy-repository'
import { createApiRequest } from './request'

export function createApiUnitOccupancyRepository(): UnitOccupancyRepository {
  const api = createApiRequest()

  return {
    list: () => api<UnitOccupancy[]>('/unit-occupancies', { query: { relations: 'user;unit' } }),
    findById: id => api<UnitOccupancy>(`/unit-occupancies/${encodeURIComponent(id)}`),
    create: (input: CreateUnitOccupancyInput) => api<UnitOccupancy>('/unit-occupancies', { method: 'POST', body: input }),
    remove: (id, endedAt) => api<UnitOccupancy>(`/unit-occupancies/${encodeURIComponent(id)}`, { method: 'DELETE', body: endedAt ? { ended_at: endedAt } : undefined }),
  }
}
