import type { CreateOccurrenceInput, FinishOccurrenceInput, Occurrence, OccurrenceAttendanceInput, OccurrenceAttachment, OccurrenceEmployee, OccurrenceHistory, OccurrenceStatus } from '~/domain/occurrence'
import type { ApiResponse } from '~/domain/common'
import type { OccurrenceRepository } from '~/repositories/contracts/occurrence-repository'
import { createApiRequest } from './request'
import { AppError } from '~/domain/app-error'

type ApiOccurrence = Occurrence & { resident_id?: string | number; unit_id?: string | number; assigned_employee_id?: string | number; occurrence_type_id?: string | number; occurrence_type?: { id?: string | number; name?: string; employee_can_close?: boolean }; resident?: { id?: string | number; name?: string }; unit?: { id?: string | number; code?: string; number?: string }; assigned_employee?: { id?: string | number; name?: string }; created_at?: string; updated_at?: string; completed_at?: string }
type ApiOccurrenceHistory = OccurrenceHistory & { occurrence_id?: string | number; user_id?: string | number; user?: { id?: string | number; name?: string }; previous_status?: OccurrenceStatus; new_status?: OccurrenceStatus; created_at?: string }
type ApiOccurrencePage = ApiResponse<ApiOccurrence[]> & { total?: number; amount?: number }
type ApiOccurrenceHistoryPage = ApiResponse<ApiOccurrenceHistory[]> & { total?: number; amount?: number }
type ApiEmployee = OccurrenceEmployee & { id: string | number }
type ApiEmployeePage = ApiResponse<ApiEmployee[]> & { total?: number; amount?: number }
type ApiAttachment = OccurrenceAttachment & { occurrence_id?: string | number; uploaded_by?: string | number; original_name?: string; mime_type?: string; created_at?: string }
type ApiAttachmentPage = ApiResponse<ApiAttachment[]> & { total?: number; amount?: number }

export function fromApiOccurrence(value: ApiOccurrence): Occurrence {
  const { resident_id, unit_id, assigned_employee_id, occurrence_type_id, occurrence_type, resident, unit, assigned_employee, created_at, updated_at, completed_at, ...occurrence } = value
  return {
    ...occurrence,
    id: String(value.id),
    residentId: resident_id != null ? String(resident_id) : value.residentId,
    unitId: unit_id != null ? String(unit_id) : value.unitId,
    assignedEmployeeId: assigned_employee_id != null ? String(assigned_employee_id) : value.assignedEmployeeId,
    occurrenceTypeId: occurrence_type_id != null ? String(occurrence_type_id) : occurrence_type?.id != null ? String(occurrence_type.id) : value.occurrenceTypeId,
    occurrenceTypeName: occurrence_type?.name ?? value.occurrenceTypeName,
    employeeCanClose: occurrence_type?.employee_can_close ?? value.employeeCanClose,
    category: occurrence_type?.name ?? value.category,
    residentName: resident?.name ?? value.residentName,
    unitCode: unit?.code ?? value.unitCode,
    unitNumber: unit?.number ?? value.unitNumber,
    assignedEmployeeName: assigned_employee?.name ?? value.assignedEmployeeName,
    createdAt: created_at ?? value.createdAt,
    updatedAt: updated_at ?? value.updatedAt,
    completedAt: completed_at ?? value.completedAt,
  }
}

export function fromApiOccurrenceHistory(value: ApiOccurrenceHistory): OccurrenceHistory {
  const { occurrence_id, user_id, user, previous_status, new_status, created_at, ...history } = value
  return {
    ...history,
    id: String(value.id),
    occurrenceId: String(occurrence_id ?? value.occurrenceId),
    userId: String(user_id ?? value.userId),
    userName: user?.name ?? value.userName,
    previousStatus: previous_status ?? value.previousStatus,
    newStatus: new_status ?? value.newStatus,
    createdAt: created_at ?? value.createdAt,
  }
}

export function fromApiOccurrenceAttachment(value: ApiAttachment): OccurrenceAttachment {
  return {
    ...value,
    id: String(value.id),
    occurrenceId: String(value.occurrence_id ?? value.occurrenceId),
    uploadedBy: String(value.uploaded_by ?? value.uploadedBy),
    originalName: value.original_name ?? value.originalName,
    mimeType: value.mime_type ?? value.mimeType,
    size: Number(value.size ?? 0),
    width: value.width == null ? undefined : Number(value.width),
    height: value.height == null ? undefined : Number(value.height),
    url: value.url,
  }
}

function mapResponse<T>(response: ApiResponse<T>, mapper: (value: any) => any): ApiResponse<T> {
  return { ...response, data: (Array.isArray(response.data) ? response.data.map(mapper) : mapper(response.data)) as T }
}

export function createApiOccurrenceRepository(): OccurrenceRepository {
  const api = createApiRequest()
  async function allPages<T>(path: string, first: ApiResponse<T[]> & { total?: number; amount?: number }, mapper: (item: T) => T extends ApiOccurrence ? Occurrence : T): Promise<ApiResponse<any[]>> {
    const total = first.total ?? first.data.length
    const pageSize = first.amount || first.data.length || 10
    const pages = Math.ceil(total / pageSize)
    if (pages <= 1) return { ...first, data: first.data.map(mapper) }
    const rest = await Promise.all(Array.from({ length: pages - 1 }, (_, index) => api<T[]>(path, { query: { page: index + 2 } })) as Promise<ApiResponse<T[]> & { total?: number; amount?: number }>[])
    return { ...first, data: [first, ...rest].flatMap(page => page.data).map(mapper) }
  }
  return {
    async list() {
      const first = await api<ApiOccurrence[]>('/occurrences') as ApiOccurrencePage
      return allPages('/occurrences', first, fromApiOccurrence) as Promise<ApiResponse<Occurrence[]>>
    },
    async findById(id) { return mapResponse(await api<ApiOccurrence>(`/occurrences/${encodeURIComponent(id)}`), fromApiOccurrence) },
    // Laravel derives the actor and unit from the authenticated session.
    create: async ({ title, description, occurrenceTypeId, residentId }: CreateOccurrenceInput) => mapResponse(await api<Occurrence>('/occurrences', { method: 'POST', body: { title, description, occurrence_type_id: Number(occurrenceTypeId), ...(residentId !== undefined ? { resident_id: Number(residentId) } : {}) } }), fromApiOccurrence),
    assign: async (id, employeeId, message) => mapResponse(await api<ApiOccurrence>(`/occurrences/${encodeURIComponent(id)}/assign`, { method: 'PATCH', body: { employee_id: Number(employeeId), ...(message?.trim() ? { message: message.trim() } : {}) } }), fromApiOccurrence),
    updateAttendance: async (id, input: OccurrenceAttendanceInput) => mapResponse(await api<ApiOccurrence>(`/occurrences/${encodeURIComponent(id)}`, { method: 'PATCH', body: input }), fromApiOccurrence),
    updateStatus: async (id, status) => mapResponse(await api<Occurrence>(`/occurrences/${encodeURIComponent(id)}/status`, { method: 'PATCH', body: { status } }), fromApiOccurrence),
    finish: async (id: string, input: FinishOccurrenceInput | string) => mapResponse(await api<Occurrence>(`/occurrences/${encodeURIComponent(id)}/finish`, { method: 'POST', body: typeof input === 'string' ? { resolved: true, conclusion: input } : input }), fromApiOccurrence),
    async history(id) {
      const path = `/occurrences/${encodeURIComponent(id)}/history`
      const first = await api<ApiOccurrenceHistory[]>(path) as ApiOccurrenceHistoryPage
      return allPages(path, first, fromApiOccurrenceHistory) as Promise<ApiResponse<OccurrenceHistory[]>>
    },
    async listAssignableEmployees(query) {
      const first = await api<ApiEmployee[]>('/assignable-employees', { query }) as ApiEmployeePage
      const total = first.total ?? first.data.length
      const pageSize = first.amount || first.data.length || 10
      const pages = Math.ceil(total / pageSize)
      if (pages <= 1) return { ...first, data: first.data.map(value => ({ id: String(value.id), name: value.name })) }
      const rest = await Promise.all(Array.from({ length: pages - 1 }, (_, index) => api<ApiEmployee[]>('/assignable-employees', { query: { ...query, page: index + 2 } })) as Promise<ApiEmployeePage>[])
      return { ...first, data: [first, ...rest].flatMap(page => page.data).map(value => ({ id: String(value.id), name: value.name })) }
    },
    async listAttachments(id) {
      const path = `/occurrences/${encodeURIComponent(id)}/attachments`
      const first = await api<ApiAttachment[]>(path) as ApiAttachmentPage
      return allPages(path, first, fromApiOccurrenceAttachment) as Promise<ApiResponse<OccurrenceAttachment[]>>
    },
    async uploadAttachment(id, file) {
      const body = new FormData()
      body.append('image', file)
      return mapResponse(await api<ApiAttachment>(`/occurrences/${encodeURIComponent(id)}/attachments`, { method: 'POST', body }), fromApiOccurrenceAttachment)
    },
    attachmentUrl(id, attachmentId) {
      const { public: config } = useRuntimeConfig()
      return `${String(config.apiBase).replace(/\/$/, '')}/occurrences/${encodeURIComponent(id)}/attachments/${encodeURIComponent(attachmentId)}`
    },
  }
}
