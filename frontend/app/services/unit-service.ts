import type { UnitRepository } from '~/repositories/contracts/unit-repository'

export function createUnitService(repository: UnitRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
  }
}

