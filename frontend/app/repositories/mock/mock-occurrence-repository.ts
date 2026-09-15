import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { Occurrence, OccurrenceHistory, OccurrenceHistoryType, OccurrenceStatus } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { mockOccurrences } from './state'
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
    occurrence.assignedEmployeeId = employeeId
    return changeStatus(occurrence, 'assigned', userId, 'assigned')
  },
  async updateStatus(id, status, userId) {
    await simulateRequest()
    return changeStatus(ensureOccurrence(id), status, userId, status === 'completed' ? 'completed' : 'status_changed')
  },
  async finish(id, userId, message) {
    await simulateRequest()
    return changeStatus(ensureOccurrence(id), 'completed', userId, 'completed', message)
  },
  async history(id) {
    await simulateRequest()
    ensureOccurrence(id)
    return response(entries.filter(item => item.occurrenceId === id))
  },
}
