import type { CreateUnitOccupancyInput } from '~/domain/unit-occupancy'
import type { UnitOccupancyRepository } from '~/repositories/contracts/unit-occupancy-repository'

export function createUnitOccupancyService(repository: UnitOccupancyRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    create: (input: CreateUnitOccupancyInput) => repository.create(input),
    remove: (id: string, endedAt?: string) => repository.remove(id, endedAt),
  }
}
