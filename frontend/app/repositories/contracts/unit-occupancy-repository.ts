import type { ApiResponse } from '~/domain/common'
import type { CreateUnitOccupancyInput, UnitOccupancy } from '~/domain/unit-occupancy'

export interface UnitOccupancyRepository {
  list(): Promise<ApiResponse<UnitOccupancy[]>>
  findById(id: string): Promise<ApiResponse<UnitOccupancy>>
  create(input: CreateUnitOccupancyInput): Promise<ApiResponse<UnitOccupancy>>
  remove(id: string, endedAt?: string): Promise<ApiResponse<UnitOccupancy>>
}
