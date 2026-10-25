import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { Occurrence, OccurrenceAttendanceInput, OccurrenceAttachment, OccurrenceHistory, OccurrenceHistoryType, OccurrenceStatus } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { mockOccurrences } from './state'
import { mockUsers } from './mock-user-repository'
import { simulateRequest } from './mock-config'
import { recordAudit } from './mock-audit-repository'
import { getMockAuthenticatedUserId } from './mock-session'
import { can } from '~/domain/permissions'

const entries: OccurrenceHistory[] = []
const attachments: OccurrenceAttachment[] = []

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensureOccurrence(id: string): Occurrence {
  const occurrence = mockOccurrences.find(item => item.id === id)
  if (!occurrence) throw new AppError('NOT_FOUND', 'Ocorrência não encontrada')
  return occurrence
}

function ensureActiveUser(id: string) {
  const user = mockUsers.find(item => item.id === id && item.status === 'active')
  if (!user) throw new AppError('FORBIDDEN', 'Usuário não autorizado')
  return user
}

function authenticatedUser() {
  const id = getMockAuthenticatedUserId()
  if (!id) throw new AppError('UNAUTHENTICATED', 'Usuário não autenticado')
  return ensureActiveUser(id)
}

function ensureAssignedEmployee(occurrence: Occurrence, userId: string, useCase: 'update-occurrence-progress' | 'finish-occurrence') {
  const user = ensureActiveUser(userId)
  if (!can(user.role, useCase, user.permissions) || occurrence.assignedEmployeeId !== userId) throw new AppError('FORBIDDEN', 'Ocorrência não atribuída a este responsável')
}

function record(occurrence: Occurrence, type: OccurrenceHistoryType, userId: string, previousStatus?: OccurrenceStatus, message?: string) {
  entries.push({ id: crypto.randomUUID(), occurrenceId: occurrence.id, type, userId, previousStatus, newStatus: occurrence.status, message, createdAt: occurrence.updatedAt })
}

function changeStatus(occurrence: Occurrence, status: OccurrenceStatus, userId: string, type: OccurrenceHistoryType, message?: string) {
  const previousStatus = occurrence.status
  occurrence.status = status
  occurrence.updatedAt = new Date().toISOString()
  occurrence.completedAt = status === 'completed' ? occurrence.updatedAt : undefined
  record(occurrence, type, userId, previousStatus, message)
  return response(occurrence)
}

export const mockOccurrenceRepository: OccurrenceRepository = {
  async list() {
    await simulateRequest()
    return response(mockOccurrences)
  },
  async findById(id) {
    await simulateRequest()
    return response(ensureOccurrence(id))
  },
  async create(input) {
    await simulateRequest()
    const resident = authenticatedUser()
    const now = new Date().toISOString()
    const occurrence: Occurrence = { title: input.title, description: input.description, category: String(input.occurrenceTypeId), occurrenceTypeId: String(input.occurrenceTypeId), residentId: input.residentId ? String(input.residentId) : resident.id, unitId: resident.unitId, id: crypto.randomUUID(), status: 'open', createdAt: now, updatedAt: now }
    mockOccurrences.push(occurrence)
    record(occurrence, 'created', resident.id)
    return response(occurrence)
  },
  async assign(id, employeeId, message) {
    await simulateRequest()
    const userId = authenticatedUser().id
    const occurrence = ensureOccurrence(id)
    const actor = ensureActiveUser(userId)
    const canAssign = can(actor.role, 'assign-occurrence', actor.permissions)
    const canHandOver = can(actor.role, 'hand-over-occurrence', actor.permissions)
    const isHandover = !canAssign && canHandOver
    if (['completed', 'cancelled'].includes(occurrence.status) || occurrence.status === 'open') throw new AppError('CONFLICT', 'Analise o chamado antes de encaminhá-lo a um funcionário.')
    if (isHandover && (occurrence.assignedEmployeeId !== userId || !['assigned', 'in_progress'].includes(occurrence.status))) throw new AppError('FORBIDDEN', 'Você só pode repassar os chamados atribuídos a você.')
    if (!canAssign && !isHandover) throw new AppError('FORBIDDEN', 'Usuário não autorizado')
    if (occurrence.assignedEmployeeId && !message?.trim()) throw new AppError('VALIDATION_ERROR', 'Informe a justificativa do repasse')
    const employee = ensureActiveUser(employeeId)
    if (employee.role !== 'employee' || employee.id === occurrence.assignedEmployeeId) throw new AppError('VALIDATION_ERROR', 'Selecione um funcionário ativo diferente do responsável atual')
    occurrence.assignedEmployeeId = employeeId
    const result = changeStatus(occurrence, 'assigned', userId, 'assigned')
    recordAudit({ userId, action: 'occurrence.assigned', entity: 'occurrence', entityId: id, metadata: { employeeId } })
    return result
  },
  async updateAttendance(id, input: OccurrenceAttendanceInput) {
    await simulateRequest()
    const userId = authenticatedUser().id
    const occurrence = ensureOccurrence(id)
    if (input.status) return changeStatus(occurrence, input.status, userId, 'status_changed', input.message)
    ensureAssignedEmployee(occurrence, userId, 'update-occurrence-progress')
    record(occurrence, 'comment', userId, undefined, input.message)
    return response(occurrence)
  },
  async updateStatus(id, status) {
    await simulateRequest()
    const userId = authenticatedUser().id
    const occurrence = ensureOccurrence(id)
    const actor = ensureActiveUser(userId)
    if (can(actor.role, 'update-occurrence-progress', actor.permissions)) {
      ensureAssignedEmployee(occurrence, userId, 'update-occurrence-progress')
      if (occurrence.status !== 'assigned' || status !== 'in_progress') throw new AppError('INVALID_STATUS_TRANSITION', 'O atendimento só pode avançar de atribuído para em andamento')
    } else if (can(actor.role, 'analyze-occurrences', actor.permissions) && occurrence.status === 'open' && status === 'analysis') {
      // análise é a única transição que a gestão faz antes do encaminhamento
    } else throw new AppError('FORBIDDEN', 'Usuário não autorizado')
    const previousStatus = occurrence.status
    const result = changeStatus(occurrence, status, userId, 'status_changed')
    recordAudit({ userId, action: 'occurrence.status_updated', entity: 'occurrence', entityId: id, metadata: { from: previousStatus, to: status } })
    return result
  },
  async finish(id, input) {
    await simulateRequest()
    const userId = authenticatedUser().id
    const occurrence = ensureOccurrence(id)
    ensureAssignedEmployee(occurrence, userId, 'finish-occurrence')
    if (occurrence.status !== 'in_progress') throw new AppError('INVALID_STATUS_TRANSITION', 'Somente atendimentos em andamento podem ser finalizados')
    const finish = typeof input === 'string' ? { resolved: true, conclusion: input } : input
    const result = changeStatus(occurrence, 'completed', userId, 'completed', finish.conclusion)
    recordAudit({ userId, action: 'occurrence.completed', entity: 'occurrence', entityId: id, metadata: finish })
    return result
  },
  async history(id) {
    await simulateRequest()
    ensureOccurrence(id)
    return response(entries.filter(item => item.occurrenceId === id))
  },
  async listAssignableEmployees(query) {
    await simulateRequest()
    const currentUserId = getMockAuthenticatedUserId()
    const employees = mockUsers
      .filter(user => user.role === 'employee' && user.status === 'active' && user.id !== currentUserId)
      .filter(user => !query?.search || user.name.toLowerCase().includes(query.search.toLowerCase()))
      .map(({ id, name }) => ({ id, name }))
    return response(employees)
  },
  async listOpenableResidents(query) {
    await simulateRequest()
    // Mesma régua da abertura: só morador ativo e com unidade.
    const residents = mockUsers
      .filter(user => user.role === 'resident' && user.status === 'active' && user.unitId)
      .filter(user => !query?.search || user.name.toLowerCase().includes(query.search.toLowerCase()))
      .map(({ id, name }) => ({ id, name }))
    return response(residents)
  },
  async listAttachments(id) {
    await simulateRequest()
    ensureOccurrence(id)
    return response(attachments.filter(item => item.occurrenceId === id))
  },
  async uploadAttachment(id, file) {
    await simulateRequest()
    const user = authenticatedUser()
    const occurrence = ensureOccurrence(id)
    if (['completed', 'cancelled'].includes(occurrence.status)) throw new AppError('CONFLICT', 'Não é possível anexar imagens a um chamado encerrado')
    const attachment: OccurrenceAttachment = {
      id: crypto.randomUUID(), occurrenceId: id, uploadedBy: user.id, originalName: file.name,
      mimeType: file.type || 'application/octet-stream', size: file.size, url: `/api/occurrences/${id}/attachments`,
    }
    attachments.push(attachment)
    record(occurrence, 'comment', user.id, undefined, `Imagem anexada: ${file.name}.`)
    return response(attachment)
  },
  async deleteAttachment(id, attachmentId) {
    await simulateRequest()
    const user = authenticatedUser()
    const occurrence = ensureOccurrence(id)
    const index = attachments.findIndex(item => item.id === attachmentId && item.occurrenceId === id)
    if (index < 0) throw new AppError('NOT_FOUND', 'Imagem não encontrada nesta ocorrência')
    if (['completed', 'cancelled'].includes(occurrence.status)) throw new AppError('CONFLICT', 'As imagens de um chamado encerrado não mudam mais')
    const attachment = attachments[index]!
    if (attachment.uploadedBy !== user.id && !can(user.role, 'close-any-occurrence', user.permissions)) throw new AppError('FORBIDDEN', 'Você só pode excluir as imagens que você enviou.')
    attachments.splice(index, 1)
    record(occurrence, 'comment', user.id, undefined, `Imagem removida: ${attachment.originalName}.`)
    return response(null)
  },
  attachmentUrl(id, attachmentId) {
    return `/api/occurrences/${encodeURIComponent(id)}/attachments/${encodeURIComponent(attachmentId)}`
  },
}
