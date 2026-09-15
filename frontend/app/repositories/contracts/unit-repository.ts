import type { ApiResponse } from '~/domain/common'
import type { Unit } from '~/domain/user'

export interface UnitRepository {
  list(): Promise<ApiResponse<Unit[]>>
  findById(id: string): Promise<ApiResponse<Unit>>
}

