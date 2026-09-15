import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { Unit } from '~/domain/user'
import type { UnitRepository } from '~/repositories/contracts/unit-repository'
import { simulateRequest } from './mock-config'

const units: Unit[] = [
  { id: 'unit-01', number: '101', block: 'A', status: 'active' },
]

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensureUnit(id: string): Unit {
  const item = units.find(item => item.id === id)
  if (!item) throw new AppError('NOT_FOUND', 'Unidade não encontrada')
  return item
}

export const mockUnitRepository: UnitRepository = {
  async list() {
    await simulateRequest()
    return response(units)
  },
  async findById(id) {
    await simulateRequest()
    return response(ensureUnit(id))
  },
}

