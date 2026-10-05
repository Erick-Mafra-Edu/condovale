import type { Occurrence, OccurrenceHistory, OccurrenceStatus } from '~/domain/occurrence'
import type { ApiResponse } from '~/domain/common'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { createApiRequest } from './request'

export function fromApiOccurrence(value: Occurrence & { resident_id?: string; unit_id?: string; assigned_employee_id?: string; created_at?: string; updated_at?: string; completed_at?: string }): Occurrence {
  const { resident_id, unit_id, assigned_employee_id, created_at, updated_at, completed_at, ...occurrence } = value
  return {
    ...occurrence,
    residentId: resident_id ?? value.residentId,
    unitId: unit_id ?? value.unitId,
    assignedEmployeeId: assigned_employee_id ?? value.assignedEmployeeId,
    createdAt: created_at ?? value.createdAt,
    updatedAt: updated_at ?? value.updatedAt,
    completedAt: completed_at ?? value.completedAt,
  }
}

export function fromApiOccurrenceHistory(value: OccurrenceHistory & { occurrence_id?: string; user_id?: string; previous_status?: OccurrenceStatus; new_status?: OccurrenceStatus; created_at?: string }): OccurrenceHistory {
  const { occurrence_id, user_id, previous_status, new_status, created_at, ...history } = value
  return {
    ...history,
    occurrenceId: occurrence_id ?? value.occurrenceId,
    userId: user_id ?? value.userId,
    previousStatus: previous_status ?? value.previousStatus,
    newStatus: new_status ?? value.newStatus,
    createdAt: created_at ?? value.createdAt,
  }
}

function mapResponse<T>(response: ApiResponse<T>, mapper: (value: any) => any): ApiResponse<T> {
  return { ...response, data: (Array.isArray(response.data) ? response.data.map(mapper) : mapper(response.data)) as T }
}

export function createApiOccurrenceRepository(): OccurrenceRepository {
  const api = createApiRequest()
  return {
    list: async () => mapResponse(await api<Occurrence[]>('/occurrences'), fromApiOccurrence),
    findById: async id => mapResponse(await api<Occurrence>(`/occurrences/${id}`), fromApiOccurrence),
    // Laravel derives resident/unit and actor from the authenticated session.
    create: async ({ title, description, category }) => mapResponse(await api<Occurrence>('/occurrences', { method: 'POST', body: { title, description, category } }), fromApiOccurrence),
    assign: async (id, employeeId) => mapResponse(await api<Occurrence>(`/occurrences/${id}/assign`, { method: 'PATCH', body: { employee_id: employeeId } }), fromApiOccurrence),
    updateStatus: async (id, status) => mapResponse(await api<Occurrence>(`/occurrences/${id}/status`, { method: 'PATCH', body: { status } }), fromApiOccurrence),
    finish: async (id: string, message?: string) => mapResponse(await api<Occurrence>(`/occurrences/${id}/finish`, { method: 'POST', body: message ? { message } : {} }), fromApiOccurrence),
    history: async id => mapResponse(await api<OccurrenceHistory[]>(`/occurrences/${id}/history`), fromApiOccurrenceHistory),
  }
}
