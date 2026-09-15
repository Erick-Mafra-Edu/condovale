import type { Occurrence, OccurrenceHistory } from '~/domain/occurrence'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { createApiRequest } from './request'

export function createApiOccurrenceRepository(): OccurrenceRepository {
  const api = createApiRequest()
  return {
    list: () => api<Occurrence[]>('/occurrences'),
    findById: id => api<Occurrence>(`/occurrences/${id}`),
    create: input => api<Occurrence>('/occurrences', { method: 'POST', body: input }),
    assign: (id, employeeId, userId) => api<Occurrence>(`/occurrences/${id}/assign`, { method: 'PATCH', body: { employeeId, userId } }),
    updateStatus: (id, status, userId) => api<Occurrence>(`/occurrences/${id}/status`, { method: 'PATCH', body: { status, userId } }),
    finish: (id, userId, message) => api<Occurrence>(`/occurrences/${id}/finish`, { method: 'POST', body: { userId, message } }),
    history: id => api<OccurrenceHistory[]>(`/occurrences/${id}/history`),
  }
}
