import { AppError } from '~/domain/app-error'
import type { CreateOccurrenceInput, OccurrenceStatus } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'

export function createOccurrenceService(repository: OccurrenceRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    async create(input: CreateOccurrenceInput) {
      const fields: Record<string, string[]> = {}
      for (const field of ['title', 'description', 'category'] as const) {
        if (!input[field].trim()) fields[field] = ['Campo obrigatório']
      }
      if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise os dados da ocorrência', fields)
      return repository.create(input)
    },
    async assign(id: string, employeeId: string) {
      if (!employeeId.trim()) throw new AppError('VALIDATION_ERROR', 'Informe o funcionário', { employeeId: ['Campo obrigatório'] })
      return repository.assign(id, employeeId)
    },
    async updateStatus(id: string, status: OccurrenceStatus) {
      return repository.updateStatus(id, status)
    },
    async finish(id: string, message?: string) {
      return repository.finish(id, message)
    },
    history: (id: string) => repository.history(id),
  }
}
