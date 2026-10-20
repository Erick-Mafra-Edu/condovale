<script setup lang="ts">
import type { CommonArea, Reservation, ReservationStatus } from '~/domain/reservation'
import { filterReservations, paginateReservations, type ReservationReportQuery } from '~/domain/reservation-report'
import { useReportExport } from '~/composables/use-report-export'

const props = withDefaults(defineProps<{
  reservations: Reservation[]
  areas?: CommonArea[]
  loading?: boolean
  error?: { message?: string } | null
  fetchAll?: (query: ReservationReportQuery) => Promise<Reservation[]>
}>(), { areas: () => [], loading: false, error: null })

const reportRef = ref<HTMLElement | null>(null)
const query = reactive<ReservationReportQuery>({ filters: { from: '2026-09-01', to: '2026-09-30', status: 'all' }, pagination: { page: 1, perPage: 5 } })
const exportReservations = ref<Reservation[] | null>(null)
const isExportingAll = ref(false)
const { exportingPdf, exportingExcel, exportExcel, exportPdf, print } = useReportExport()

const filteredReservations = computed(() => filterReservations(props.reservations, query.filters))
const displayedReservations = computed(() => isExportingAll.value
  ? exportReservations.value ?? filteredReservations.value
  : paginateReservations(filteredReservations.value, query.pagination.page, query.pagination.perPage))
const totalPages = computed(() => Math.max(1, Math.ceil(filteredReservations.value.length / query.pagination.perPage)))
const approvedCount = computed(() => filteredReservations.value.filter(item => item.status === 'approved').length)
const pendingCount = computed(() => filteredReservations.value.filter(item => item.status === 'pending').length)
const cancelledCount = computed(() => filteredReservations.value.filter(item => item.status === 'cancelled').length)
const exportRows = computed(() => (exportReservations.value ?? filteredReservations.value).map(item => ({
  Data: new Intl.DateTimeFormat('pt-BR').format(new Date(`${item.date}T12:00:00`)),
  Área: props.areas?.find(area => area.id === item.areaId)?.name ?? item.areaId,
  Horário: item.startTime && item.endTime ? `${item.startTime}–${item.endTime}` : 'Dia inteiro',
  Status: statusLabel(item.status),
  Morador: item.residentId,
})))
const filenameSuffix = computed(() => `${query.filters.from}-${query.filters.to}`)

function statusLabel(status: ReservationStatus) {
  return ({ pending: 'Pendente', approved: 'Aprovada', rejected: 'Recusada', cancelled: 'Cancelada' }[status])
}

function areaName(id: string) { return props.areas?.find(area => area.id === id)?.name ?? id }
function setStatus(status: ReservationReportQuery['filters']['status']) { query.filters.status = status }
function changePage(page: number) { query.pagination.page = Math.min(Math.max(1, page), totalPages.value) }

async function prepareExport() {
  const exportQuery: ReservationReportQuery = { filters: { ...query.filters }, pagination: { page: 1, perPage: 0 }, exportAll: true }
  exportReservations.value = props.fetchAll ? await props.fetchAll(exportQuery) : filteredReservations.value
  isExportingAll.value = true
  await nextTick()
}

async function exportPdfReport() {
  try { await prepareExport(); await exportPdf(reportRef.value, `relatorio-reservas-${filenameSuffix.value}`) }
  finally { exportReservations.value = null; isExportingAll.value = false }
}

async function exportExcelReport() {
  try { await prepareExport(); await exportExcel(exportRows.value, `relatorio-reservas-${filenameSuffix.value}`) }
  finally { exportReservations.value = null; isExportingAll.value = false }
}

watch(() => [query.filters.from, query.filters.to, query.filters.status], () => { query.pagination.page = 1 })
</script>

<template>
  <section ref="reportRef" class="reservation-report report-page">
    <div class="report-toolbar no-print"><div><span class="eyebrow">Visão da administração</span><h2>Relatório de reservas</h2><p>Acompanhe a utilização das áreas comuns no período.</p></div><div class="report-actions"><button class="outline-button" :disabled="exportingExcel || loading" @click="exportExcelReport">{{ exportingExcel ? 'Gerando...' : 'Excel' }}</button><button class="outline-button" :disabled="exportingPdf || loading" @click="exportPdfReport">{{ exportingPdf ? 'Gerando...' : 'PDF' }}</button><button class="primary-button" @click="print">Imprimir</button></div></div>
    <div class="report-filters no-print"><ReportDatePicker v-model="query.filters.from" label="De" /><ReportDatePicker v-model="query.filters.to" label="Até" /><label>Status<select v-model="query.filters.status"><option value="all">Todos</option><option value="pending">Pendentes</option><option value="approved">Aprovadas</option><option value="rejected">Recusadas</option><option value="cancelled">Canceladas</option></select></label></div>
    <div class="report-tabs no-print"><button :class="{ selected: query.filters.status === 'all' }" @click="setStatus('all')">Todas</button><button :class="{ selected: query.filters.status === 'pending' }" @click="setStatus('pending')">Pendentes</button><button :class="{ selected: query.filters.status === 'approved' }" @click="setStatus('approved')">Aprovadas</button></div>
    <header class="report-print-header"><span class="eyebrow">CondoVale · Residencial Vale Verde</span><h1>Relatório de reservas</h1><p>Período: {{ query.filters.from }} a {{ query.filters.to }}</p></header>
    <div class="report-summary"><article><small>Total de reservas</small><strong>{{ filteredReservations.length }}</strong><span>no período selecionado</span></article><article><small>Aprovadas</small><strong>{{ approvedCount }}</strong><span>reservas confirmadas</span></article><article><small>Pendentes</small><strong>{{ pendingCount }}</strong><span>aguardando análise</span></article><article><small>Canceladas</small><strong>{{ cancelledCount }}</strong><span>reservas canceladas</span></article></div>
    <article class="report-card report-table-card"><div class="report-card-head"><div><h3>Detalhamento das reservas</h3><p>{{ filteredReservations.length }} registros encontrados · página {{ query.pagination.page }} de {{ totalPages }}</p></div></div><div v-if="loading" class="empty-row" role="status">Carregando reservas...</div><div v-else-if="error" class="empty-row error-row" role="alert">{{ error.message || 'Não foi possível carregar as reservas.' }}</div><div v-else class="report-table-wrap"><table><thead><tr><th>Data</th><th>Área comum</th><th>Horário</th><th>Status</th></tr></thead><tbody><tr v-for="item in displayedReservations" :key="item.id"><td>{{ new Date(`${item.date}T12:00:00`).toLocaleDateString('pt-BR') }}</td><td><strong>{{ areaName(item.areaId) }}</strong></td><td>{{ item.startTime && item.endTime ? `${item.startTime}–${item.endTime}` : 'Dia inteiro' }}</td><td><span class="status-pill">{{ statusLabel(item.status) }}</span></td></tr><tr v-if="!filteredReservations.length"><td colspan="4" class="empty-row">Nenhuma reserva encontrada para os filtros.</td></tr></tbody></table></div><div v-if="totalPages > 1 && !isExportingAll && !loading && !error" class="report-pagination no-print"><button :disabled="query.pagination.page === 1" @click="changePage(query.pagination.page - 1)">Anterior</button><span>Página {{ query.pagination.page }} de {{ totalPages }}</span><button :disabled="query.pagination.page === totalPages" @click="changePage(query.pagination.page + 1)">Próxima</button></div></article>
  </section>
</template>

<style scoped>
.reservation-report{max-width:1180px;margin:0 auto;padding-bottom:40px;color:#102a43}.report-toolbar{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;margin-bottom:22px}.report-toolbar h2{margin:8px 0 5px;font-size:30px;letter-spacing:-.04em}.report-toolbar p,.report-card-head p{margin:0;color:#64748b;font-size:13px}.report-actions{display:flex;gap:9px}.report-actions button{min-height:40px}.report-filters{display:flex;gap:12px;margin-bottom:14px}.report-filters label{display:grid;gap:6px;font-size:12px;font-weight:700;color:#36566d}.report-filters select{min-width:155px;border:1px solid #d8e6e8;border-radius:10px;padding:10px 12px;background:#fff;color:#102a43}.report-tabs{display:flex;gap:22px;border-bottom:1px solid #e4ecee;margin-bottom:26px}.report-tabs button{padding:10px 2px 12px;border:0;border-bottom:2px solid transparent;background:none;color:#64748b;font:inherit;cursor:pointer}.report-tabs button.selected{border-color:#008c9e;color:#008c9e;font-weight:800}.report-print-header{display:none}.report-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}.report-summary article,.report-card{background:#fff;border:1px solid #e4ecee;border-radius:18px;box-shadow:0 4px 16px rgba(16,42,67,.05)}.report-summary article{padding:18px}.report-summary small,.report-summary span{display:block;color:#64748b;font-size:12px}.report-summary strong{display:block;font-size:28px;margin:9px 0 3px;letter-spacing:-.05em}.report-table-card{padding:0;overflow:hidden}.report-card-head{display:flex;justify-content:space-between;align-items:flex-start;padding:20px 20px 16px}.report-card-head h3{margin:0 0 5px;font-size:16px}.report-table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:13px 20px;border-top:1px solid #e4ecee;white-space:nowrap}th{color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.08em}.status-pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:#e7f8f9;color:#006a78;font-size:11px;font-weight:800}.empty-row{text-align:center;color:#64748b;padding:30px}.error-row{color:#b4232c}.report-pagination{display:flex;justify-content:flex-end;align-items:center;gap:12px;padding:14px 20px;border-top:1px solid #e4ecee;color:#64748b;font-size:12px}.report-pagination button{border:1px solid #d8e6e8;border-radius:8px;background:#fff;padding:7px 10px;color:#36566d;cursor:pointer}.report-pagination button:disabled{opacity:.45;cursor:not-allowed}@media(max-width:800px){.report-toolbar{display:block}.report-actions{margin-top:18px}.report-summary{grid-template-columns:1fr 1fr}.report-filters{display:grid;grid-template-columns:1fr 1fr}.report-filters select{min-width:0;width:100%}}@media print{.no-print{display:none!important}.reservation-report{max-width:none;padding:0}.report-print-header{display:block;margin-bottom:18px}.report-summary{grid-template-columns:repeat(4,1fr)}.report-card,.report-summary article{box-shadow:none;break-inside:avoid}}@media(prefers-color-scheme:dark){.reservation-report{color:#f1f7f8}.report-summary article,.report-card{background:#0d2d37;border-color:rgba(180,215,225,.12)}.report-toolbar p,.report-card-head p,.report-summary small,.report-summary span{color:#a5bcc4}.report-filters label{color:#b9d1d8}.report-filters select{background:#0a2935;border-color:#285263;color:#f1f7f8}.report-tabs{border-color:rgba(180,215,225,.14)}th,td{border-color:rgba(180,215,225,.12)}th{color:#a5bcc4}.status-pill{background:#164e58;color:#b8f5f5}}
</style>
