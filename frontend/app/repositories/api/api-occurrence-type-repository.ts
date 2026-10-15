import type { ApiResponse } from '~/domain/common'
import type { CreateOccurrenceTypeInput, OccurrenceType, UpdateOccurrenceTypeInput } from '~/domain/occurrence-type'
import type { OccurrenceTypeRepository } from '~/repositories/contracts/occurrence-type-repository'
import { createApiRequest } from './request'

type ApiOccurrenceType = Omit<OccurrenceType, 'id' | 'employeeCanClose' | 'description'> & { id: string | number; description?: string | null; employee_can_close?: boolean; employeeCanClose?: boolean }

export function fromApiOccurrenceType(value: ApiOccurrenceType): OccurrenceType {
  return { ...value, id: String(value.id), description: value.description ?? undefined, employeeCanClose: value.employee_can_close ?? value.employeeCanClose ?? false }
}

const toApi = (input: CreateOccurrenceTypeInput | UpdateOccurrenceTypeInput) => {
  const { employeeCanClose, ...rest } = input
  return { ...rest, ...(employeeCanClose !== undefined ? { employee_can_close: employeeCanClose } : {}) }
}

const map = (response: ApiResponse<ApiOccurrenceType>): ApiResponse<OccurrenceType> => ({ ...response, data: fromApiOccurrenceType(response.data) })

export function createApiOccurrenceTypeRepository(): OccurrenceTypeRepository {
  const api = createApiRequest()
  return {
    async list(query) {
      const response = await api<ApiOccurrenceType[]>('/occurrence-types', { query: { ...(query?.active !== undefined ? { filters: `active:=:${query.active ? 1 : 0}` } : {}), ...(query?.search ? { search: query.search } : {}) } })
      return { ...response, data: response.data.map(fromApiOccurrenceType) }
    },
    async findById(id) { return map(await api<ApiOccurrenceType>(`/occurrence-types/${encodeURIComponent(id)}`)) },
    async create(input) { return map(await api<ApiOccurrenceType>('/occurrence-types', { method: 'POST', body: toApi(input) })) },
    async update(id, input) { return map(await api<ApiOccurrenceType>(`/occurrence-types/${encodeURIComponent(id)}`, { method: 'PATCH', body: toApi(input) })) },
    async remove(id) { return await api<null>(`/occurrence-types/${encodeURIComponent(id)}`, { method: 'DELETE' }) },
  }
}
