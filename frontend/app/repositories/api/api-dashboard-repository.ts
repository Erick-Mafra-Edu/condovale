import type { DashboardRepository } from '~/repositories/contracts/dashboard-repository'
import { createApiRequest } from './request'

export function createApiDashboardRepository(): DashboardRepository {
  const request = createApiRequest()
  return { get: () => request('/dashboard') }
}
