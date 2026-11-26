import type { DashboardData } from '~/domain/dashboard'
import { useServices } from '~/services'
import { toAppError } from '~/domain/app-error'

export function useDashboard() {
  const data = ref<DashboardData | null>(null)
  const loading = ref(false)
  const error = ref('')
  const { dashboardService } = useServices()

  async function refresh() {
    loading.value = true
    error.value = ''
    try { data.value = (await dashboardService.get()).data }
    catch (cause) { error.value = toAppError(cause).message }
    finally { loading.value = false }
  }

  return { data, loading, error, refresh }
}
