<script setup lang="ts">
import type { ReportFilters, OccurrenceReportRow, ReservationReportRow } from '~/domain/operational-report'
import { useOperationalReports } from '~/composables/use-operational-reports'
import { useReportExport } from '~/composables/use-report-export'

const props = defineProps<{ kind: 'occurrences' | 'reservations' }>()
const { occurrences, reservations, loading, error, load, exportAll } = useOperationalReports()
const { exportingPdf, exportingExcel, exportExcel, exportPdf } = useReportExport()
const reportRef = ref<HTMLElement | null>(null)
const today = new Date()
const dateKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const from = ref(dateKey(new Date(today.getFullYear(), today.getMonth(), 1)))
const to = ref(dateKey(today))
const status = ref('')
const nature = ref('')
const typeId = ref('')
const areaId = ref('')
const page = ref(1)
const feedback = ref('')
const report = computed(() => props.kind === 'occurrences' ? occurrences.value : reservations.value)
const summary = computed(() => report.value?.summary)
const rows = computed(() => props.kind === 'occurrences' ? occurrences.value?.occurrences : reservations.value?.reservations)
const summaryEntries = computed(() => summary.value ? Object.entries(summary.value).map(([key, value]) => ({ key, value })) : [])
const label: Record<string, string> = { total: 'Total', open: 'Abertas', completed: 'Concluídas', cancelled: 'Canceladas', resolved: 'Resolvidas', unresolved: 'Não resolvidas', average_resolution_hours: 'Tempo médio (h)', pending: 'Pendentes', approved: 'Aprovadas', rejected: 'Reprovadas' }

function filters(exportMode = false): ReportFilters { return { from: from.value, to: to.value, page: page.value, limit: 10, ...(status.value ? { status: status.value } : {}), ...(props.kind === 'occurrences' ? { ...(nature.value ? { kind: nature.value } : {}), ...(typeId.value ? { occurrence_type_id: typeId.value } : {}) } : areaId.value ? { common_area_id: areaId.value } : {}), ...(exportMode ? { export: true } : {}) } }
async function refresh() { if (!from.value || !to.value || from.value > to.value) { feedback.value = 'Informe um período válido.'; return }; feedback.value = ''; await load(props.kind, filters()) }
watch(() => props.kind, () => { page.value = 1; status.value = ''; nature.value = ''; areaId.value = ''; typeId.value = ''; void refresh() })
onMounted(refresh)
async function changePage(next: number) { page.value = next; await refresh() }
function occurrenceRows(): OccurrenceReportRow[] { return occurrences.value?.occurrences.data ?? [] }
function reservationRows(): ReservationReportRow[] { return reservations.value?.reservations.data ?? [] }
async function exportReport(format: 'pdf' | 'excel') {
  feedback.value = ''
  try {
    const full = await exportAll(props.kind, filters(true))
    const name = `relatorio-${props.kind}-${from.value}-${to.value}`
    if (format === 'excel') {
      const data = 'occurrences' in full ? full.occurrences.data.map(item => ({ Data: item.created_at, Título: item.title, Natureza: item.occurrence_type?.kind ?? '', Status: item.status })) : full.reservations.data.map(item => ({ Data: item.date, Área: item.common_area?.name ?? '', Morador: item.resident?.name ?? '', Unidade: item.resident?.unit?.code ?? '', Status: item.status }))
      await exportExcel(data, name)
    } else {
      const current = props.kind === 'occurrences' ? occurrences.value : reservations.value
      if ('occurrences' in full) occurrences.value = full
      else reservations.value = full
      await nextTick()
      try { await exportPdf(reportRef.value, name) }
      finally { if (props.kind === 'occurrences') occurrences.value = current as typeof occurrences.value; else reservations.value = current as typeof reservations.value }
    }
  } catch (cause) { feedback.value = `${cause instanceof Error ? cause.message : 'Falha na exportação.'} Reduza o período e tente novamente.` }
}
</script>

<template>
  <section ref="reportRef" class="operational-report"><div class="report-head"><div><span class="eyebrow">Relatório operacional</span><h2>{{ props.kind === 'occurrences' ? 'Ocorrências' : 'Reservas' }}</h2></div><div class="report-actions"><button class="outline-button" :disabled="exportingExcel || loading" @click="exportReport('excel')">Excel</button><button class="outline-button" :disabled="exportingPdf || loading" @click="exportReport('pdf')">PDF</button></div></div>
    <form class="report-filters" @submit.prevent="page = 1; refresh()"><ReportDatePicker v-model="from" label="De" /><ReportDatePicker v-model="to" label="Até" /><label>Situação<select v-model="status"><option value="">Todas</option><option v-for="value in props.kind === 'occurrences' ? ['open','analysis','assigned','in_progress','completed','cancelled'] : ['pending','approved','rejected','cancelled']" :key="value" :value="value">{{ value }}</option></select></label><label v-if="props.kind === 'occurrences'">Natureza<select v-model="nature"><option value="">Todas</option><option value="maintenance">Manutenção</option><option value="complaint">Denúncia</option></select></label><label v-if="props.kind === 'occurrences'">ID do tipo<input v-model="typeId" inputmode="numeric" /></label><label v-else>Área comum<select v-model="areaId"><option value="">Todas</option><option v-for="area in reservations?.by_area ?? []" :key="area.common_area_id" :value="area.common_area_id">{{ area.name }}</option></select></label><button class="primary-button" type="submit" :disabled="loading">Aplicar filtros</button></form>
    <p class="filter-help">Os indicadores resumem todo o período. Situação, natureza, tipo e área filtram apenas o detalhamento.</p><p v-if="loading" role="status">Carregando relatório...</p><p v-if="error || feedback" class="form-error" role="alert">{{ error || feedback }} <button v-if="error" class="outline-button" @click="refresh">Tentar novamente</button></p>
    <template v-if="report"><div class="report-summary"><article v-for="entry in summaryEntries" :key="entry.key" class="panel"><small>{{ label[entry.key] ?? entry.key }}</small><strong>{{ entry.value ?? '—' }}</strong></article></div><div class="report-distribution panel"><h3>{{ props.kind === 'occurrences' ? 'Por natureza e tipo' : 'Por área comum' }}</h3><div v-if="props.kind === 'occurrences'"><p v-for="item in occurrences?.by_kind ?? []" :key="item.kind">{{ item.label }}: {{ item.total }}</p><p v-for="item in occurrences?.by_type ?? []" :key="item.occurrence_type_id">{{ item.name }}: {{ item.total }}</p></div><p v-for="item in reservations?.by_area ?? []" v-else :key="item.common_area_id">{{ item.name }}: {{ item.total }}</p></div><article class="panel report-detail"><h3>Detalhamento</h3><div class="table-wrap"><table><thead><tr><th>Data</th><th>{{ props.kind === 'occurrences' ? 'Ocorrência' : 'Área comum' }}</th><th>Situação</th><th v-if="props.kind === 'reservations'">Morador e unidade</th></tr></thead><tbody><template v-if="props.kind === 'occurrences'"><tr v-for="item in occurrenceRows()" :key="item.id"><td>{{ item.created_at }}</td><td>{{ item.title }}</td><td>{{ item.status }}</td></tr></template><template v-else><tr v-for="item in reservationRows()" :key="item.id"><td>{{ item.date }} {{ item.start_time && item.end_time ? `${item.start_time}–${item.end_time}` : 'Dia inteiro' }}</td><td>{{ item.common_area?.name }}</td><td>{{ item.status }}</td><td>{{ item.resident?.name ?? 'Indisponível' }} · {{ item.resident?.unit?.code ?? '—' }}</td></tr></template><tr v-if="!rows?.data.length"><td colspan="4">Nenhum registro para os filtros.</td></tr></tbody></table></div><nav v-if="rows && rows.last_page > 1" class="pagination"><button class="outline-button" :disabled="page <= 1" @click="changePage(page - 1)">Anterior</button><span>{{ page }} de {{ rows.last_page }} · {{ rows.total }} registros</span><button class="outline-button" :disabled="page >= rows.last_page" @click="changePage(page + 1)">Próxima</button></nav></article></template>
  </section>
</template>

<style scoped>
.operational-report{color:var(--navy)}.report-head,.report-actions,.report-filters,.pagination{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}.report-filters{justify-content:flex-start;margin:18px 0}.report-filters label{display:grid;gap:5px}.report-filters input,.report-filters select{background:var(--input);color:var(--navy);border:1px solid var(--line);border-radius:8px;padding:8px}.report-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px}.report-summary article,.report-detail,.report-distribution{padding:16px}.report-summary small{display:block;color:var(--neutral)}.report-summary strong{display:block;font-size:26px}.report-distribution{margin:14px 0}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid var(--line)}.pagination{justify-content:flex-end;margin-top:14px}@media print{.report-actions,.report-filters,.pagination{display:none}}
.filter-help{color:var(--neutral);font-size:12px}
</style>
