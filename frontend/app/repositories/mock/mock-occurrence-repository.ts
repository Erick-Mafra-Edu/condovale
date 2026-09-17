import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { Occurrence, OccurrenceHistory, OccurrenceHistoryType, OccurrenceStatus } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { mockOccurrences } from './state'
import { mockUsers } from './mock-user-repository'
import { simulateRequest } from './mock-config'

const entries: OccurrenceHistory[] = []

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

function ensureAssignedEmployee(occurrence: Occurrence, userId: string) {
  const user = ensureActiveUser(userId)
  if (user.role !== 'employee' || occurrence.assignedEmployeeId !== userId) throw new AppError('FORBIDDEN', 'Ocorrência não atribuída a este funcionário')
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
    const now = new Date().toISOString()
    const occurrence: Occurrence = { ...input, id: crypto.randomUUID(), status: 'open', createdAt: now, updatedAt: now }
    mockOccurrences.push(occurrence)
    record(occurrence, 'created', input.residentId)
    return response(occurrence)
  },
  async assign(id, employeeId, userId) {
    await simulateRequest()
    const occurrence = ensureOccurrence(id)
    if (ensureActiveUser(userId).role !== 'admin') throw new AppError('FORBIDDEN', 'Somente a administração pode atribuir ocorrências')
    const employee = ensureActiveUser(employeeId)
    if (employee.role !== 'employee') throw new AppError('VALIDATION_ERROR', 'Selecione um funcionário ativo')
    occurrence.assignedEmployeeId = employeeId
    return changeStatus(occurrence, 'assigned', userId, 'assigned')
  },
  async updateStatus(id, status, userId) {
    await simulateRequest()
    const occurrence = ensureOccurrence(id)
    const actor = ensureActiveUser(userId)
    if (actor.role === 'employee') {
      ensureAssignedEmployee(occurrence, userId)
      if (occurrence.status !== 'assigned' || status !== 'in_progress') throw new AppError('INVALID_STATUS_TRANSITION', 'O atendimento só pode avançar de atribuído para em andamento')
    } else if (actor.role !== 'admin') throw new AppError('FORBIDDEN', 'Usuário não autorizado')
    return changeStatus(occurrence, status, userId, 'status_changed')
  },
  async finish(id, userId, message) {
    await simulateRequest()
    const occurrence = ensureOccurrence(id)
    ensureAssignedEmployee(occurrence, userId)
    if (occurrence.status !== 'in_progress') throw new AppError('INVALID_STATUS_TRANSITION', 'Somente atendimentos em andamento podem ser finalizados')
    return changeStatus(occurrence, 'completed', userId, 'completed', message)
  },
  async history(id) {
    await simulateRequest()
    ensureOccurrence(id)
    return response(entries.filter(item => item.occurrenceId === id))
  },
}
