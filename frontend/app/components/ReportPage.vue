<script setup lang="ts">
import type { Occurrence } from '~/domain/occurrence'
import { useReportExport } from '~/composables/use-report-export'

const props = defineProps<{ occurrences: Occurrence[] }>()
const reportRef = ref<HTMLElement | null>(null)
const chartRef = ref<HTMLElement | null>(null)
const period = ref('2026-09')
const category = ref('all')
const tab = ref<'all' | 'open' | 'completed'>('all')
let chart: { dispose: () => void; setOption: (option: unknown) => void } | null = null
const { exportingPdf, exportingExcel, exportExcel, exportPdf, print } = useReportExport()

const periodOccurrences = computed(() => props.occurrences.filter(item => item.createdAt.startsWith(period.value)))
const filteredOccurrences = computed(() => periodOccurrences.value.filter(item => {
  const matchesCategory = category.value === 'all' || item.category === category.value
  const matchesTab = tab.value === 'all' || tab.value === 'open' && !['completed', 'cancelled'].includes(item.status) || tab.value === 'completed' && item.status === 'completed'
  return matchesCategory && matchesTab
}))
const categories = computed(() => [...new Set(periodOccurrences.value.map(item => item.category))])
const openCount = computed(() => periodOccurrences.value.filter(item => !['completed', 'cancelled'].includes(item.status)).length)
const completedCount = computed(() => periodOccurrences.value.filter(item => item.status === 'completed').length)
const averageResolution = computed(() => {
  const resolved = periodOccurrences.value.filter(item => item.completedAt)
  if (!resolved.length) return '—'
  const hours = resolved.reduce((total, item) => total + (new Date(item.completedAt!).getTime() - new Date(item.createdAt).getTime()) / 3600000, 0) / resolved.length
  return `${Math.max(1, Math.round(hours))}h`
})
const chartData = computed(() => categories.value.map(item => ({ name: item, value: periodOccurrences.value.filter(occurrence => occurrence.category === item).length })))
const reportRows = computed(() => filteredOccurrences.value.map(item => ({
  Data: new Intl.DateTimeFormat('pt-BR').format(new Date(item.createdAt)),
  Título: item.title,
  Categoria: item.category,
  Status: item.status,
  Morador: item.residentId,
})))

function statusLabel(status: Occurrence['status']) {
  return ({ open: 'Aberta', analysis: 'Em análise', assigned: 'Atribuída', in_progress: 'Em andamento', completed: 'Concluída', cancelled: 'Cancelada' }[status])
}

async function renderChart() {
  if (!import.meta.client || !chartRef.value) return
  const { init } = await import('echarts')
  chart?.dispose()
  chart = init(chartRef.value)
  chart.setOption({
    animation: false,
    color: ['#008c9e'],
    grid: { left: 38, right: 20, top: 24, bottom: 34 },
    xAxis: { type: 'category', data: chartData.value.map(item => item.name), axisLabel: { color: '#64748b' } },
    yAxis: { type: 'value', minInterval: 1, axisLabel: { color: '#64748b' }, splitLine: { lineStyle: { color: '#e4ecee' } } },
    series: [{ type: 'bar', barMaxWidth: 42, data: chartData.value.map(item => item.value), itemStyle: { borderRadius: [8, 8, 0, 0] } }],
  })
}

watch([period, category, tab], renderChart)
onMounted(renderChart)
onBeforeUnmount(() => chart?.dispose())
</script>

<template>
  <section ref="reportRef" class="report-page">
    <div class="report-toolbar no-print"><div><span class="eyebrow">Visão da administração</span><h2>Relatórios do condomínio</h2><p>Acompanhe as ocorrências e a qualidade dos atendimentos.</p></div><div class="report-actions"><button class="outline-button" :disabled="exportingExcel" @click="exportExcel(reportRows, `relatorio-ocorrencias-${period}`)">{{ exportingExcel ? 'Gerando...' : 'Excel' }}</button><button class="outline-button" :disabled="exportingPdf" @click="exportPdf(reportRef, `relatorio-ocorrencias-${period}`)">{{ exportingPdf ? 'Gerando...' : 'PDF' }}</button><button class="primary-button" @click="print">Imprimir</button></div></div>
    <div class="report-filters no-print"><label>Período<select v-model="period"><option value="2026-09">Setembro 2026</option><option value="2026-08">Agosto 2026</option></select></label><label>Categoria<select v-model="category"><option value="all">Todas</option><option v-for="item in categories" :key="item" :value="item">{{ item }}</option></select></label></div>
    <div class="report-tabs no-print"><button v-for="item in [{ key: 'all', label: 'Todos' }, { key: 'open', label: 'Abertos' }, { key: 'completed', label: 'Concluídos' }]" :key="item.key" :class="{ selected: tab === item.key }" @click="tab = item.key as 'all' | 'open' | 'completed'">{{ item.label }}</button></div>
    <header class="report-print-header"><span class="eyebrow">CondoVale · Residencial Vale Verde</span><h1>Relatório de ocorrências</h1><p>Período: {{ period }} · Gerado em {{ new Date().toLocaleDateString('pt-BR') }}</p></header>
    <div class="report-summary"><article><small>Total de ocorrências</small><strong>{{ periodOccurrences.length }}</strong><span>no período selecionado</span></article><article><small>Em acompanhamento</small><strong>{{ openCount }}</strong><span>ocorrências abertas</span></article><article><small>Concluídas</small><strong>{{ completedCount }}</strong><span>atendimentos finalizados</span></article><article><small>Tempo médio</small><strong>{{ averageResolution }}</strong><span>até a conclusão</span></article></div>
    <div class="report-grid"><article class="report-card chart-card"><div class="report-card-head"><div><h3>Ocorrências por categoria</h3><p>Distribuição no período selecionado</p></div></div><div ref="chartRef" class="report-chart" aria-label="Gráfico de ocorrências por categoria"></div></article><article class="report-card"><div class="report-card-head"><div><h3>Status atual</h3><p>Visão rápida do atendimento</p></div></div><div class="status-list"><div><span class="status-dot open"></span><span>Abertas</span><strong>{{ openCount }}</strong></div><div><span class="status-dot done"></span><span>Concluídas</span><strong>{{ completedCount }}</strong></div><div><span class="status-dot muted"></span><span>Canceladas</span><strong>{{ periodOccurrences.filter(item => item.status === 'cancelled').length }}</strong></div></div></article></div>
    <article class="report-card report-table-card"><div class="report-card-head"><div><h3>Detalhamento das ocorrências</h3><p>{{ filteredOccurrences.length }} registros encontrados</p></div></div><div class="report-table-wrap"><table><thead><tr><th>Data</th><th>Título</th><th>Categoria</th><th>Status</th></tr></thead><tbody><tr v-for="item in filteredOccurrences" :key="item.id"><td>{{ new Date(item.createdAt).toLocaleDateString('pt-BR') }}</td><td><strong>{{ item.title }}</strong><small>{{ item.description }}</small></td><td>{{ item.category }}</td><td><span class="status-pill">{{ statusLabel(item.status) }}</span></td></tr><tr v-if="!filteredOccurrences.length"><td colspan="4" class="empty-row">Nenhuma ocorrência encontrada para os filtros.</td></tr></tbody></table></div><div class="mobile-report-list"><div v-for="item in filteredOccurrences" :key="item.id"><strong>{{ item.title }}</strong><small>{{ new Date(item.createdAt).toLocaleDateString('pt-BR') }} · {{ item.category }}</small><span class="status-pill">{{ statusLabel(item.status) }}</span></div></div></article>
  </section>
</template>

<style scoped>
.report-page{max-width:1180px;margin:0 auto;padding-bottom:40px;color:#102a43}.report-toolbar{display:flex;justify-content:space-between;gap:24px;align-items:flex-end;margin-bottom:22px}.report-toolbar h2{margin:8px 0 5px;font-size:30px;letter-spacing:-.04em}.report-toolbar p,.report-card-head p{margin:0;color:#64748b;font-size:13px}.report-actions{display:flex;gap:9px}.report-actions button{min-height:40px}.report-filters{display:flex;gap:12px;margin-bottom:14px}.report-filters label{display:grid;gap:6px;font-size:12px;font-weight:700;color:#36566d}.report-filters select{min-width:180px;border:1px solid #d8e6e8;border-radius:10px;padding:10px 12px;background:#fff;color:#102a43}.report-tabs{display:flex;gap:22px;border-bottom:1px solid #e4ecee;margin-bottom:26px}.report-tabs button{padding:10px 2px 12px;border:0;border-bottom:2px solid transparent;background:none;color:#64748b;font:inherit;cursor:pointer}.report-tabs button.selected{border-color:#008c9e;color:#008c9e;font-weight:800}.report-print-header{display:none}.report-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}.report-summary article,.report-card{background:#fff;border:1px solid #e4ecee;border-radius:18px;box-shadow:0 4px 16px rgba(16,42,67,.05)}.report-summary article{padding:18px}.report-summary small,.report-summary span{display:block;color:#64748b;font-size:12px}.report-summary strong{display:block;font-size:28px;margin:9px 0 3px;letter-spacing:-.05em}.report-grid{display:grid;grid-template-columns:1.35fr .65fr;gap:16px;margin-bottom:16px}.report-card{padding:20px}.report-card-head{display:flex;justify-content:space-between;align-items:flex-start}.report-card h3{margin:0 0 5px;font-size:16px}.report-chart{height:220px;margin-top:12px}.status-list{display:grid;gap:20px;margin-top:30px}.status-list div{display:grid;grid-template-columns:10px 1fr auto;align-items:center;gap:10px;color:#36566d;font-size:13px}.status-list strong{color:#102a43}.status-dot{width:8px;height:8px;border-radius:50%;background:#008c9e}.status-dot.done{background:#48b58d}.status-dot.muted{background:#a8b8c0}.report-table-card{padding:0;overflow:hidden}.report-table-card .report-card-head{padding:20px 20px 16px}.report-table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:13px 20px;border-top:1px solid #e4ecee;white-space:nowrap}th{color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.08em}td small{display:block;max-width:350px;overflow:hidden;text-overflow:ellipsis;color:#64748b;margin-top:4px}.status-pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:#e7f8f9;color:#006a78;font-size:11px;font-weight:800}.mobile-report-list{display:none}.empty-row{text-align:center;color:#64748b;padding:30px}@media(max-width:800px){.report-toolbar{display:block}.report-actions{margin-top:18px}.report-summary{grid-template-columns:1fr 1fr}.report-grid{grid-template-columns:1fr}.report-filters select{min-width:0}.report-table-wrap{display:none}.mobile-report-list{display:grid}.mobile-report-list>div{display:grid;gap:5px;padding:14px 20px;border-top:1px solid #e4ecee}.mobile-report-list small{color:#64748b}.mobile-report-list .status-pill{width:max-content}}@media print{.no-print{display:none!important}.report-page{max-width:none;padding:0}.report-print-header{display:block;margin-bottom:18px}.report-summary{grid-template-columns:repeat(4,1fr)}.report-card,.report-summary article{box-shadow:none;break-inside:avoid}.report-grid{grid-template-columns:1.35fr .65fr}.report-table-card{margin-top:16px}.report-table-wrap{display:block}.mobile-report-list{display:none}}
@media(prefers-color-scheme:dark){.report-page{color:#f1f7f8}.report-summary article,.report-card{background:#0d2d37;border-color:rgba(180,215,225,.12)}.report-toolbar p,.report-card-head p,.report-summary small,.report-summary span,td small{color:#a5bcc4}.report-filters label{color:#b9d1d8}.report-filters select{background:#0a2935;border-color:#285263;color:#f1f7f8}.report-tabs{border-color:rgba(180,215,225,.14)}th,td,.mobile-report-list>div{border-color:rgba(180,215,225,.12)}th{color:#a5bcc4}.status-list div,.status-list strong{color:#d4e5e8}.status-pill{background:#164e58;color:#b8f5f5}}
@media(prefers-color-scheme:dark){.report-page.pdf-light{background:#fff;color:#102a43}.report-page.pdf-light .report-summary article,.report-page.pdf-light .report-card{background:#fff;border-color:#e4ecee;color:#102a43}.report-page.pdf-light .report-toolbar p,.report-page.pdf-light .report-card-head p,.report-page.pdf-light .report-summary small,.report-page.pdf-light .report-summary span,.report-page.pdf-light td small{color:#64748b}.report-page.pdf-light th,.report-page.pdf-light td,.report-page.pdf-light .mobile-report-list>div{border-color:#e4ecee}.report-page.pdf-light th{color:#64748b}.report-page.pdf-light .status-list div,.report-page.pdf-light .status-list strong{color:#36566d}.report-page.pdf-light .status-pill{background:#e7f8f9;color:#006a78}}
</style>
