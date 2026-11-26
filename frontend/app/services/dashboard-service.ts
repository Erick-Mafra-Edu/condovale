import type { DashboardRepository } from '~/repositories/contracts/dashboard-repository'
export function createDashboardService(repository: DashboardRepository) { return { get: () => repository.get() } }
