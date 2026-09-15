import { AppError } from '~/domain/app-error'
import type { CreateOccurrenceInput, OccurrenceStatus } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'

export function createOccurrenceService(repository: OccurrenceRepository) {
  function requireUser(userId: string) {
    if (!userId.trim()) throw new AppError('VALIDATION_ERROR', 'Informe o responsável pela alteração', { userId: ['Informe o usuário'] })
  }

  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    async create(input: CreateOccurrenceInput) {
      const fields: Record<string, string[]> = {}
      for (const field of ['title', 'description', 'category', 'residentId'] as const) {
        if (!input[field].trim()) fields[field] = ['Campo obrigatório']
      }
      if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise os dados da ocorrência', fields)
      return repository.create(input)
    },
    async assign(id: string, employeeId: string, userId: string) {
      requireUser(userId)
      if (!employeeId.trim()) throw new AppError('VALIDATION_ERROR', 'Informe o funcionário', { employeeId: ['Campo obrigatório'] })
      return repository.assign(id, employeeId, userId)
    },
    async updateStatus(id: string, status: OccurrenceStatus, userId: string) {
      requireUser(userId)
      return repository.updateStatus(id, status, userId)
    },
    async finish(id: string, userId: string, message?: string) {
      requireUser(userId)
      return repository.finish(id, userId, message)
    },
    history: (id: string) => repository.history(id),
  }
}
