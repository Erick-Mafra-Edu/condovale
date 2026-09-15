import type { Unit } from '~/domain/user'
import type { UnitRepository } from '~/repositories/contracts/unit-repository'
import { createApiRequest } from './request'

export function createApiUnitRepository(): UnitRepository {
  const api = createApiRequest()

  return {
    list: () => api<Unit[]>('/units'),
    findById: id => api<Unit>(`/units/${encodeURIComponent(id)}`),
  }
}

