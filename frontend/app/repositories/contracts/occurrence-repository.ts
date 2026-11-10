import type { ApiResponse } from '~/domain/common'
import type { CreateOccurrenceInput, FinishOccurrenceInput, Occurrence, OccurrenceAttendanceInput, OccurrenceAttachment, OccurrenceEmployee, OccurrenceHistory, OccurrenceResident, OccurrenceStatus } from '~/domain/occurrence'

export interface OccurrenceRepository {
  list(): Promise<ApiResponse<Occurrence[]>>
  findById(id: string): Promise<ApiResponse<Occurrence>>
  create(input: CreateOccurrenceInput): Promise<ApiResponse<Occurrence>>
  assign(id: string, employeeId: string, message?: string): Promise<ApiResponse<Occurrence>>
  updateAttendance(id: string, input: OccurrenceAttendanceInput): Promise<ApiResponse<Occurrence>>
  updateStatus(id: string, status: OccurrenceStatus): Promise<ApiResponse<Occurrence>>
  finish(id: string, input: FinishOccurrenceInput | string): Promise<ApiResponse<Occurrence>>
  history(id: string): Promise<ApiResponse<OccurrenceHistory[]>>
  listAssignableEmployees(query?: { search?: string }): Promise<ApiResponse<OccurrenceEmployee[]>>
  listOpenableResidents(query?: { search?: string }): Promise<ApiResponse<OccurrenceResident[]>>
  listAttachments(id: string): Promise<ApiResponse<OccurrenceAttachment[]>>
  uploadAttachment(id: string, file: File): Promise<ApiResponse<OccurrenceAttachment>>
  deleteAttachment(id: string, attachmentId: string): Promise<ApiResponse<null>>
  attachmentUrl(id: string, attachmentId: string): string
}
