import type { OccurrenceReport, ReportFilters, ReservationReport } from '~/domain/operational-report'
import { useServices } from '~/services'
import { toAppError } from '~/domain/app-error'

export function useOperationalReports() {
  const { reportService } = useServices()
  const occurrences = ref<OccurrenceReport | null>(null)
  const reservations = ref<ReservationReport | null>(null)
  const loading = ref(false)
  const error = ref('')
  async function load(kind: 'occurrences' | 'reservations', filters: ReportFilters) {
    loading.value = true; error.value = ''
    try {
      if (kind === 'occurrences') occurrences.value = (await reportService.occurrences(filters)).data
      else reservations.value = (await reportService.reservations(filters)).data
    } catch (cause) { error.value = toAppError(cause).message }
    finally { loading.value = false }
  }
  async function exportAll(kind: 'occurrences' | 'reservations', filters: ReportFilters) {
    return kind === 'occurrences' ? (await reportService.occurrences({ ...filters, page: 1, export: true })).data : (await reportService.reservations({ ...filters, page: 1, export: true })).data
  }
  return { occurrences, reservations, loading, error, load, exportAll }
}
