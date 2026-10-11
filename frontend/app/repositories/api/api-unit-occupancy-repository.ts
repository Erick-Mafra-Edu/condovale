import type { CreateUnitOccupancyInput, UnitOccupancy } from '~/domain/unit-occupancy'
import type { ApiResponse } from '~/domain/common'
import type { UnitOccupancyRepository } from '~/repositories/contracts/unit-occupancy-repository'
import { createApiRequest } from './request'
import { fromApiUnit } from './api-unit-repository'
import { fromApiUser } from './api-user-repository'

type ApiOccupancyListResponse = ApiResponse<ApiOccupancy[]> & { total?: number; amount?: number }

type ApiOccupancy = Omit<UnitOccupancy, 'id' | 'user_id' | 'unit_id' | 'user' | 'unit'> & {
  id: string | number
  user_id: string | number
  unit_id: string | number
  user?: Parameters<typeof fromApiUser>[0]
  unit?: Parameters<typeof fromApiUnit>[0]
}

export function fromApiUnitOccupancy(value: ApiOccupancy): UnitOccupancy {
  return {
    ...value,
    id: String(value.id),
    user_id: String(value.user_id),
    unit_id: String(value.unit_id),
    user: value.user ? fromApiUser(value.user) : undefined,
    unit: value.unit ? fromApiUnit(value.unit) : undefined,
  }
}

function mapOccupancy(response: { data: ApiOccupancy; message: string | null }) {
  return { ...response, data: fromApiUnitOccupancy(response.data) }
}

export function createApiUnitOccupancyRepository(): UnitOccupancyRepository {
  const api = createApiRequest()

  return {
    async list() {
      const first = await api<ApiOccupancy[]>('/unit-occupancies', { query: { relations: 'user;unit' } }) as ApiOccupancyListResponse
      const total = first.total ?? first.data.length
      const pageSize = first.amount || first.data.length || 10
      const pages = Math.ceil(total / pageSize)
      if (pages <= 1) return { ...first, data: first.data.map(fromApiUnitOccupancy) }
      const rest = await Promise.all(Array.from({ length: pages - 1 }, (_, index) => api<ApiOccupancy[]>('/unit-occupancies', { query: { relations: 'user;unit', page: index + 2 } })) as Promise<ApiOccupancyListResponse>[])
      return { ...first, data: [first, ...rest].flatMap(page => page.data).map(fromApiUnitOccupancy) }
    },
    async findById(id) { return mapOccupancy(await api<ApiOccupancy>(`/unit-occupancies/${encodeURIComponent(id)}`)) },
    async create(input: CreateUnitOccupancyInput) { return mapOccupancy(await api<ApiOccupancy>('/unit-occupancies', { method: 'POST', body: input })) },
    async remove(id, endedAt) { return mapOccupancy(await api<ApiOccupancy>(`/unit-occupancies/${encodeURIComponent(id)}`, { method: 'DELETE', body: endedAt ? { ended_at: endedAt } : undefined })) },
  }
}
