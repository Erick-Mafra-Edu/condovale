import type { ApiResponse } from '~/domain/common'
import type { DashboardData } from '~/domain/dashboard'

export interface DashboardRepository {
  get(): Promise<ApiResponse<DashboardData>>
}
