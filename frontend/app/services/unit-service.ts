import { AppError } from '~/domain/app-error'
import type { CreateUnitInput, UpdateUnitInput } from '~/domain/user'
import type { UnitRepository } from '~/repositories/contracts/unit-repository'

function validate(input: CreateUnitInput | UpdateUnitInput) {
  const fields: Record<string, string[]> = {}
  if ('code' in input && input.code !== undefined && !input.code.trim()) fields.code = ['Informe o código da unidade']
  if ('number' in input && input.number !== undefined && !input.number.trim()) fields.number = ['Informe o número da unidade']
  if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise os campos informados', fields)
}

export function createUnitService(repository: UnitRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    async create(input: CreateUnitInput) {
      validate(input)
      return repository.create(input)
    },
    async update(id: string, input: UpdateUnitInput) {
      validate(input)
      return repository.update(id, input)
    },
    deactivate: (id: string) => repository.deactivate(id),
  }
}

