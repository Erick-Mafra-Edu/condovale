import type { ApiResponse } from '~/domain/common'
import type { CreateOccurrenceTypeInput, OccurrenceType, UpdateOccurrenceTypeInput } from '~/domain/occurrence-type'
import { AppError } from '~/domain/app-error'
import type { OccurrenceTypeRepository } from '~/repositories/contracts/occurrence-type-repository'

const types: OccurrenceType[] = [
  { id: '1', name: 'Manutenção', description: 'Reparos e conservação', employeeCanClose: true, active: true },
  { id: '2', name: 'Convivência', employeeCanClose: false, active: true },
  { id: '3', name: 'Segurança', employeeCanClose: false, active: true },
]
const response = <T>(data: T): ApiResponse<T> => ({ data: structuredClone(data), message: null })
export const mockOccurrenceTypeRepository: OccurrenceTypeRepository = {
  async list(query) { return response(types.filter(item => query?.active === undefined || item.active === query.active)) },
  async findById(id) { const item = types.find(type => type.id === id); if (!item) throw new AppError('NOT_FOUND', 'Tipo de ocorrência não encontrado'); return response(item) },
  async create(input) { const item = { ...input, id: crypto.randomUUID() }; types.push(item); return response(item) },
  async update(id, input) { const item = types.find(type => type.id === id); if (!item) throw new AppError('NOT_FOUND', 'Tipo de ocorrência não encontrado'); Object.assign(item, input); return response(item) },
  async remove(id) { const item = types.find(type => type.id === id); if (item) item.active = false; return response(null) },
}
