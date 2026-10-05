import type { ApiResponse } from '~/domain/common'
import type { CreateOccurrenceInput, Occurrence, OccurrenceHistory, OccurrenceStatus } from '~/domain/occurrence'

export interface OccurrenceRepository {
  list(): Promise<ApiResponse<Occurrence[]>>
  findById(id: string): Promise<ApiResponse<Occurrence>>
  create(input: CreateOccurrenceInput): Promise<ApiResponse<Occurrence>>
  assign(id: string, employeeId: string): Promise<ApiResponse<Occurrence>>
  updateStatus(id: string, status: OccurrenceStatus): Promise<ApiResponse<Occurrence>>
  finish(id: string, message?: string): Promise<ApiResponse<Occurrence>>
  history(id: string): Promise<ApiResponse<OccurrenceHistory[]>>
}
