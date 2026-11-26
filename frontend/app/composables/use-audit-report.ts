import type { AuditFilters, AuditReport } from '~/domain/audit-report'
import { useServices } from '~/services'
import { toAppError } from '~/domain/app-error'

export function useAuditReport() {
  const { auditReportService } = useServices()
  const data = ref<AuditReport | null>(null)
  const loading = ref(false)
  const error = ref('')
  async function load(filters: AuditFilters) { loading.value = true; error.value = ''; try { data.value = (await auditReportService.get(filters)).data } catch (cause) { error.value = toAppError(cause).message } finally { loading.value = false } }
  async function exportAll(filters: AuditFilters) { return (await auditReportService.get({ ...filters, page: 1, export: true })).data }
  return { data, loading, error, load, exportAll }
}
