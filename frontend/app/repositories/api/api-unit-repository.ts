import type { Unit } from '~/domain/user'
import type { ApiResponse } from '~/domain/common'
import type { UnitRepository } from '~/repositories/contracts/unit-repository'
import { createApiRequest } from './request'

type ApiUnit = Omit<Unit, 'id' | 'block'> & { id: string | number; block?: string | null }
type ApiUnitListResponse = ApiResponse<ApiUnit[]> & { total?: number; amount?: number }

export function fromApiUnit(value: ApiUnit): Unit {
  return { ...value, id: String(value.id), block: value.block ?? undefined }
}

function mapUnit(response: { data: ApiUnit; message: string | null }) {
  return { ...response, data: fromApiUnit(response.data) }
}

export function createApiUnitRepository(): UnitRepository {
  const api = createApiRequest()

  return {
    async list() {
      const first = await api<ApiUnit[]>('/units') as ApiUnitListResponse
      const total = first.total ?? first.data.length
      const pageSize = first.amount || first.data.length || 10
      const pages = Math.ceil(total / pageSize)
      if (pages <= 1) return { ...first, data: first.data.map(fromApiUnit) }
      const rest = await Promise.all(Array.from({ length: pages - 1 }, (_, index) => api<ApiUnit[]>('/units', { query: { page: index + 2 } })) as Promise<ApiUnitListResponse>[])
      return { ...first, data: [first, ...rest].flatMap(page => page.data).map(fromApiUnit) }
    },
    async findById(id) { return mapUnit(await api<ApiUnit>(`/units/${encodeURIComponent(id)}`)) },
    async create(input) { return mapUnit(await api<ApiUnit>('/units', { method: 'POST', body: input })) },
    async update(id, input) { return mapUnit(await api<ApiUnit>(`/units/${encodeURIComponent(id)}`, { method: 'PATCH', body: input })) },
    async deactivate(id) { return mapUnit(await api<ApiUnit>(`/units/${encodeURIComponent(id)}/deactivate`, { method: 'POST' })) },
  }
}

