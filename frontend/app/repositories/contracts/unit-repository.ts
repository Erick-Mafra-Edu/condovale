import type { ApiResponse } from '~/domain/common'
import type { CreateUnitInput, Unit, UpdateUnitInput } from '~/domain/user'

export interface UnitRepository {
  list(): Promise<ApiResponse<Unit[]>>
  findById(id: string): Promise<ApiResponse<Unit>>
  create(input: CreateUnitInput): Promise<ApiResponse<Unit>>
  update(id: string, input: UpdateUnitInput): Promise<ApiResponse<Unit>>
  deactivate(id: string): Promise<ApiResponse<Unit>>
}

