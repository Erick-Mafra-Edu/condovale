import { AppError } from '~/domain/app-error'
import type { CreateOccurrenceInput, FinishOccurrenceInput, OccurrenceStatus } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'

export function createOccurrenceService(repository: OccurrenceRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    async create(input: CreateOccurrenceInput) {
      const fields: Record<string, string[]> = {}
      for (const field of ['title', 'description'] as const) {
        if (!input[field].trim()) fields[field] = ['Campo obrigatório']
      }
      if (input.occurrenceTypeId === '' || input.occurrenceTypeId === undefined || input.occurrenceTypeId === null) fields.occurrenceTypeId = ['Campo obrigatório']
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
    async finish(id: string, input: FinishOccurrenceInput | string) {
      if (typeof input === 'string') input = { resolved: true, conclusion: input }
      if (!input.conclusion.trim()) throw new AppError('VALIDATION_ERROR', 'Informe a conclusão', { conclusion: ['Campo obrigatório'] })
      return repository.finish(id, input)
    },
    history: (id: string) => repository.history(id),
  }
}
