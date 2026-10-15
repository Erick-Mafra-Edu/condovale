import type { CreateOccurrenceInput, FinishOccurrenceInput, Occurrence, OccurrenceHistory, OccurrenceStatus } from '~/domain/occurrence'
import type { ApiResponse } from '~/domain/common'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { createApiRequest } from './request'
import { AppError } from '~/domain/app-error'

const unavailable = (operation: string): never => { throw new AppError('NOT_AVAILABLE', `${operation} de ocorrências ainda não está disponível na API.`) }

export function fromApiOccurrence(value: Occurrence & { resident_id?: string | number; unit_id?: string | number; assigned_employee_id?: string | number; occurrence_type_id?: string | number; occurrence_type?: { id?: string | number; name?: string }; created_at?: string; updated_at?: string; completed_at?: string }): Occurrence {
  const { resident_id, unit_id, assigned_employee_id, occurrence_type_id, occurrence_type, created_at, updated_at, completed_at, ...occurrence } = value
  return {
    ...occurrence,
    residentId: resident_id != null ? String(resident_id) : value.residentId,
    unitId: unit_id != null ? String(unit_id) : value.unitId,
    assignedEmployeeId: assigned_employee_id != null ? String(assigned_employee_id) : value.assignedEmployeeId,
    occurrenceTypeId: occurrence_type_id != null ? String(occurrence_type_id) : occurrence_type?.id != null ? String(occurrence_type.id) : value.occurrenceTypeId,
    occurrenceTypeName: occurrence_type?.name ?? value.occurrenceTypeName,
    category: occurrence_type?.name ?? value.category,
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
    list: async () => unavailable('A listagem'),
    findById: async () => unavailable('O detalhamento'),
    // Laravel derives the actor and unit from the authenticated session.
    create: async ({ title, description, occurrenceTypeId, residentId }: CreateOccurrenceInput) => mapResponse(await api<Occurrence>('/occurrences', { method: 'POST', body: { title, description, occurrence_type_id: Number(occurrenceTypeId), ...(residentId !== undefined ? { resident_id: Number(residentId) } : {}) } }), fromApiOccurrence),
    // Assignment, listing, detail and history are not registered by Laravel yet.
    assign: async () => unavailable('A atribuição'),
    updateStatus: async (id, status) => mapResponse(await api<Occurrence>(`/occurrences/${id}/status`, { method: 'PATCH', body: { status } }), fromApiOccurrence),
    finish: async (id: string, input: FinishOccurrenceInput | string) => mapResponse(await api<Occurrence>(`/occurrences/${id}/finish`, { method: 'POST', body: typeof input === 'string' ? { resolved: true, conclusion: input } : input }), fromApiOccurrence),
    history: async () => unavailable('O histórico'),
  }
}
