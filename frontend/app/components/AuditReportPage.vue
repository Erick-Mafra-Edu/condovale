<script setup lang="ts">
import type { AuditLog } from '~/domain/audit'
import type { User } from '~/domain/user'
import { useReportExport } from '~/composables/use-report-export'

const props = defineProps<{ logs: AuditLog[]; users: User[] }>()
const reportRef = ref<HTMLElement | null>(null)
const from = ref('2026-09-01')
const to = ref('2026-09-30')
const entity = ref<AuditLog['entity'] | 'all'>('all')
const actor = ref('all')
const page = ref(1)
const perPage = 8
const exportingAll = ref(false)
const { exportingPdf, exportingExcel, exportExcel, exportPdf, print } = useReportExport()

const userById = computed(() => new Map(props.users.map(user => [user.id, user])))
const filteredLogs = computed(() => props.logs.filter(log => {
  const date = log.createdAt.slice(0, 10)
  return date >= from.value && date <= to.value
    && (entity.value === 'all' || log.entity === entity.value)
    && (actor.value === 'all' || log.userId === actor.value)
}).sort((a, b) => b.createdAt.localeCompare(a.createdAt)))
const totalPages = computed(() => Math.max(1, Math.ceil(filteredLogs.value.length / perPage)))
const visibleLogs = computed(() => exportingAll.value ? filteredLogs.value : filteredLogs.value.slice((page.value - 1) * perPage, page.value * perPage))
const adminActions = computed(() => filteredLogs.value.filter(log => userById.value.get(log.userId)?.role === 'admin').length)
const employeeActions = computed(() => filteredLogs.value.filter(log => userById.value.get(log.userId)?.role === 'employee').length)
const actors = computed(() => [...new Set(props.logs.map(log => log.userId))].map(id => ({ id, name: userById.value.get(id)?.name ?? id })))

const actionLabels: Record<string, string> = {
  'occurrence.assigned': 'Ocorrência atribuída',
  'occurrence.status_updated': 'Andamento atualizado',
  'occurrence.completed': 'Atendimento finalizado',
  'reservation.approved': 'Reserva aprovada',
  'reservation.rejected': 'Reserva reprovada',
  'user.deactivated': 'Usuário inativado',
  'notice.published': 'Comunicado publicado',
}
const entityLabels: Record<AuditLog['entity'], string> = { reservation: 'Reserva', occurrence: 'Ocorrência', notice: 'Comunicado', user: 'Usuário', unit: 'Unidade' }

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function formatMetadata(metadata?: Record<string, unknown>) {
  if (!metadata) return '—'
  return Object.entries(metadata).map(([key, value]) => `${key}: ${String(value)}`).join(' · ')
}

function changePage(value: number) {
  page.value = Math.min(Math.max(1, value), totalPages.value)
}

watch([from, to, entity, actor], () => { page.value = 1 })

async function preparePdf() {
  exportingAll.value = true
  await nextTick()
}

async function exportPdfReport() {
  try {
    await preparePdf()
    await exportPdf(reportRef.value, `relatorio-auditoria-${from.value}-${to.value}`)
  } finally { exportingAll.value = false }
}

async function exportExcelReport() {
  await exportExcel(filteredLogs.value.map(log => ({
    'Data e hora': formatDateTime(log.createdAt),
    Usuário: userById.value.get(log.userId)?.name ?? log.userId,
    Ação: actionLabels[log.action] ?? log.action,
    Entidade: entityLabels[log.entity],
    Registro: log.entityId,
    Detalhes: formatMetadata(log.metadata),
  })), `relatorio-auditoria-${from.value}-${to.value}`)
}
</script>

<template>
  <section ref="reportRef" class="report-page audit-report">
    <div class="report-toolbar no-print"><div><span class="eyebrow">Controle administrativo</span><h2>Relatório de auditoria</h2><p>Consulte as operações críticas realizadas no condomínio.</p></div><div class="report-actions"><button class="outline-button" :disabled="exportingExcel" @click="exportExcelReport">{{ exportingExcel ? 'Gerando...' : 'Excel' }}</button><button class="outline-button" :disabled="exportingPdf" @click="exportPdfReport">{{ exportingPdf ? 'Gerando...' : 'PDF' }}</button><button class="primary-button" @click="print">Imprimir</button></div></div>
    <div class="audit-filters no-print"><ReportDatePicker v-model="from" label="De" /><ReportDatePicker v-model="to" label="Até" /><label>Entidade<select v-model="entity"><option value="all">Todas</option><option value="occurrence">Ocorrência</option><option value="reservation">Reserva</option><option value="notice">Comunicado</option><option value="user">Usuário</option><option value="unit">Unidade</option></select></label><label>Responsável<select v-model="actor"><option value="all">Todos</option><option v-for="item in actors" :key="item.id" :value="item.id">{{ item.name }}</option></select></label></div>
    <header class="report-print-header"><span class="eyebrow">CondoVale · Auditoria</span><h1>Relatório de auditoria</h1><p>Período: {{ from }} a {{ to }} · Gerado em {{ new Date().toLocaleDateString('pt-BR') }}</p></header>
    <div class="audit-summary"><article><small>Eventos encontrados</small><strong>{{ filteredLogs.length }}</strong><span>no período selecionado</span></article><article><small>Ações administrativas</small><strong>{{ adminActions }}</strong><span>realizadas por administradores</span></article><article><small>Ações de funcionários</small><strong>{{ employeeActions }}</strong><span>em atendimentos atribuídos</span></article></div>
    <article class="audit-card"><div class="audit-card-head"><div><h3>Eventos registrados</h3><p>{{ filteredLogs.length }} registros · página {{ page }} de {{ totalPages }}</p></div></div><div class="audit-table-wrap"><table><thead><tr><th>Data e hora</th><th>Responsável</th><th>Ação</th><th>Entidade</th><th>Registro</th><th>Detalhes</th></tr></thead><tbody><tr v-for="log in visibleLogs" :key="log.id"><td>{{ formatDateTime(log.createdAt) }}</td><td><strong>{{ userById.get(log.userId)?.name ?? log.userId }}</strong><small>{{ userById.get(log.userId)?.role ?? 'sistema' }}</small></td><td><span class="audit-action">{{ actionLabels[log.action] ?? log.action }}</span></td><td>{{ entityLabels[log.entity] }}</td><td><code>{{ log.entityId }}</code></td><td class="metadata">{{ formatMetadata(log.metadata) }}</td></tr><tr v-if="!filteredLogs.length"><td colspan="6" class="empty-row">Nenhum evento encontrado para os filtros.</td></tr></tbody></table></div><div v-if="totalPages > 1 && !exportingAll" class="report-pagination no-print"><button :disabled="page === 1" @click="changePage(page - 1)">Anterior</button><span>Página {{ page }} de {{ totalPages }}</span><button :disabled="page === totalPages" @click="changePage(page + 1)">Próxima</button></div></article>
  </section>
</template>

<style scoped>
.report-page{max-width:1180px;margin:0 auto 40px;color:#102a43}.report-toolbar{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;margin-bottom:22px}.report-toolbar h2{margin:8px 0 5px;font-size:30px}.report-toolbar p,.audit-card-head p{margin:0;color:#64748b;font-size:13px}.report-actions{display:flex;gap:9px}.audit-filters{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:12px;margin-bottom:18px}.audit-filters label{display:grid;gap:6px;font-size:12px;font-weight:700;color:#36566d}.audit-filters select{width:100%;border:1px solid #d8e6e8;border-radius:10px;padding:10px 12px;background:#fff;color:#102a43}.report-print-header{display:none}.audit-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}.audit-summary article,.audit-card{background:#fff;border:1px solid #e4ecee;border-radius:18px;box-shadow:0 4px 16px rgba(16,42,67,.05)}.audit-summary article{padding:18px}.audit-summary small,.audit-summary span{display:block;color:#64748b;font-size:12px}.audit-summary strong{display:block;margin:9px 0 3px;font-size:28px}.audit-card{overflow:hidden}.audit-card-head{padding:20px}.audit-card h3{margin:0 0 5px}.audit-table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{text-align:left;padding:13px 16px;border-top:1px solid #e4ecee;vertical-align:top}th{color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.07em}td small{display:block;color:#64748b;margin-top:4px}.audit-action{display:inline-flex;padding:5px 9px;border-radius:999px;background:#e7f8f9;color:#006a78;font-weight:800}.metadata{min-width:180px;color:#64748b;white-space:normal}.report-pagination{display:flex;justify-content:flex-end;align-items:center;gap:12px;padding:14px 20px;border-top:1px solid #e4ecee;color:#64748b;font-size:12px}.report-pagination button{border:1px solid #d8e6e8;border-radius:8px;background:#fff;padding:7px 10px}.report-pagination button:disabled{opacity:.45}.empty-row{text-align:center;color:#64748b;padding:30px}@media(max-width:800px){.report-toolbar{display:block}.report-actions{margin-top:18px}.audit-filters{grid-template-columns:1fr 1fr}.audit-summary{grid-template-columns:1fr}.audit-table-wrap{overflow:auto}}@media print{.no-print{display:none!important}.report-page{max-width:none}.report-print-header{display:block;margin-bottom:18px}.audit-card,.audit-summary article{box-shadow:none;break-inside:avoid}}@media(prefers-color-scheme:dark){.report-page{color:#f1f7f8}.audit-summary article,.audit-card{background:#0d2d37;border-color:rgba(180,215,225,.12)}.report-toolbar p,.audit-card-head p,.audit-summary small,.audit-summary span,td small,.metadata{color:#a5bcc4}.audit-filters label{color:#b9d1d8}.audit-filters select{background:#0a2935;border-color:#285263;color:#f1f7f8}th,td,.report-pagination{border-color:rgba(180,215,225,.12)}th{color:#a5bcc4}.audit-action{background:#164e58;color:#b8f5f5}}@media(prefers-color-scheme:dark){.report-page.pdf-light{background:#fff;color:#102a43}.report-page.pdf-light .audit-summary article,.report-page.pdf-light .audit-card{background:#fff;border-color:#e4ecee;color:#102a43}.report-page.pdf-light th,.report-page.pdf-light td{border-color:#e4ecee}.report-page.pdf-light th,.report-page.pdf-light td small,.report-page.pdf-light .metadata,.report-page.pdf-light .audit-summary small,.report-page.pdf-light .audit-summary span{color:#64748b}.report-page.pdf-light .audit-action{background:#e7f8f9;color:#006a78}}
</style>
