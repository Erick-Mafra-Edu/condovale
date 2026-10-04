import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { CreateUnitOccupancyInput, UnitOccupancy } from '~/domain/unit-occupancy'
import type { UnitOccupancyRepository } from '~/repositories/contracts/unit-occupancy-repository'
import { simulateRequest } from './mock-config'

const occupancies: UnitOccupancy[] = []

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensure(id: string) {
  const item = occupancies.find(item => item.id === id)
  if (!item) throw new AppError('NOT_FOUND', 'Vínculo não encontrado')
  return item
}

export const mockUnitOccupancyRepository: UnitOccupancyRepository = {
  async list() { await simulateRequest(); return response(occupancies) },
  async findById(id) { await simulateRequest(); return response(ensure(id)) },
  async create(input: CreateUnitOccupancyInput) {
    await simulateRequest()
    const item: UnitOccupancy = { ...structuredClone(input), id: crypto.randomUUID(), occupant_type: input.occupant_type ?? 'tenant', started_at: input.started_at ?? new Date().toISOString().slice(0, 10), ended_at: null, is_active: true }
    occupancies.push(item)
    return response(item)
  },
  async remove(id, endedAt) {
    await simulateRequest()
    const item = ensure(id)
    item.ended_at = endedAt ?? new Date().toISOString().slice(0, 10)
    item.is_active = false
    return response(item)
  },
}
