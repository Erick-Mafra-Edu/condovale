import { toAppError, type AppError } from '~/domain/app-error'
import type { AuditLog } from '~/domain/audit'
import { useServices } from '~/services'

export function useAuditLogs() {
  const { auditService } = useServices()
  const auditLogs = ref<AuditLog[]>([])
  const loading = ref(false)
  const error = ref<AppError | null>(null)

  async function loadAuditLogs() {
    loading.value = true
    error.value = null
    try {
      auditLogs.value = (await auditService.list()).data
    } catch (cause) {
      error.value = toAppError(cause)
      throw error.value
    } finally { loading.value = false }
  }

  return { auditLogs, loading, error, loadAuditLogs }
}
