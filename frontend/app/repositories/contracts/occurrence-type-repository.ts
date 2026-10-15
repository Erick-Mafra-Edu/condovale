import type { ApiResponse } from '~/domain/common'
import type { CreateOccurrenceTypeInput, OccurrenceType, UpdateOccurrenceTypeInput } from '~/domain/occurrence-type'

export interface OccurrenceTypeRepository {
  list(query?: { active?: boolean; search?: string }): Promise<ApiResponse<OccurrenceType[]>>
  findById(id: string): Promise<ApiResponse<OccurrenceType>>
  create(input: CreateOccurrenceTypeInput): Promise<ApiResponse<OccurrenceType>>
  update(id: string, input: UpdateOccurrenceTypeInput): Promise<ApiResponse<OccurrenceType>>
  remove(id: string): Promise<ApiResponse<OccurrenceType | null>>
}
