import { AppError } from '~/domain/app-error'
import type { CreateOccurrenceTypeInput, UpdateOccurrenceTypeInput } from '~/domain/occurrence-type'
import type { OccurrenceTypeRepository } from '~/repositories/contracts/occurrence-type-repository'

export function createOccurrenceTypeService(repository: OccurrenceTypeRepository) {
  return {
    list: (query?: { active?: boolean; search?: string }) => repository.list(query),
    findById: (id: string) => repository.findById(id),
    create: (input: CreateOccurrenceTypeInput) => {
      if (!input.name.trim()) throw new AppError('VALIDATION_ERROR', 'Informe o nome do tipo', { name: ['Campo obrigatório'] })
      return repository.create(input)
    },
    update: (id: string, input: UpdateOccurrenceTypeInput) => repository.update(id, input),
    remove: (id: string) => repository.remove(id),
  }
}
