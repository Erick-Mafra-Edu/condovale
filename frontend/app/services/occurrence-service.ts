import { AppError } from '~/domain/app-error'
import type { CreateOccurrenceInput, FinishOccurrenceInput, OccurrenceAttendanceInput, OccurrenceStatus } from '~/domain/occurrence'
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
    async assign(id: string, employeeId: string, message?: string) {
      if (!employeeId.trim()) throw new AppError('VALIDATION_ERROR', 'Informe o funcionário', { employeeId: ['Campo obrigatório'] })
      if (message && message.length > 255) throw new AppError('VALIDATION_ERROR', 'A justificativa deve ter no máximo 255 caracteres', { message: ['A justificativa deve ter no máximo 255 caracteres'] })
      return message === undefined
        ? repository.assign(id, employeeId)
        : repository.assign(id, employeeId, message)
    },
    async updateAttendance(id: string, input: OccurrenceAttendanceInput) {
      const fields: Record<string, string[]> = {}
      if (!input.status && !input.message?.trim()) fields.message = ['Informe o novo status ou descreva a atualização do atendimento']
      if (input.message && input.message.length > 2000) fields.message = ['A atualização deve ter no máximo 2000 caracteres']
      if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise a atualização do atendimento', fields)
      return repository.updateAttendance(id, { ...input, message: input.message?.trim() || undefined })
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
    assignableEmployees: (query?: { search?: string }) => repository.listAssignableEmployees(query),
    openableResidents: (query?: { search?: string }) => repository.listOpenableResidents(query),
    listAttachments: (id: string) => repository.listAttachments(id),
    uploadAttachment: (id: string, file: File) => repository.uploadAttachment(id, file),
    deleteAttachment: (id: string, attachmentId: string) => repository.deleteAttachment(id, attachmentId),
    attachmentUrl: (id: string, attachmentId: string) => repository.attachmentUrl(id, attachmentId),
  }
}
