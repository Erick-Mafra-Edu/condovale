<script setup lang="ts">
import type { AuditFilters, AuditReportRow } from '~/domain/audit-report'
import { useAuditReport } from '~/composables/use-audit-report'
import { useReportExport } from '~/composables/use-report-export'

const { data, loading, error, load, exportAll } = useAuditReport()
const { exportExcel, exportPdf, exportingExcel, exportingPdf } = useReportExport()
const reportRef = ref<HTMLElement | null>(null)
const now = new Date()
const dateKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const from = ref(dateKey(new Date(now.getFullYear(), now.getMonth(), 1)))
const to = ref(dateKey(now))
const entity = ref('')
const entityId = ref('')
const userId = ref('')
const role = ref('')
const action = ref('')
const page = ref(1)
const selected = ref<AuditReportRow | null>(null)
const feedback = ref('')
function filters(): AuditFilters { return { from: from.value, to: to.value, page: page.value, limit: 10, ...(entity.value ? { entity: entity.value } : {}), ...(entityId.value ? { entity_id: entityId.value } : {}), ...(userId.value ? { user_id: userId.value } : {}), ...(role.value ? { role: role.value } : {}), ...(action.value ? { action: action.value } : {}) } }
async function refresh() { if (!from.value || !to.value || from.value > to.value) { feedback.value = 'Informe um período válido.'; return }; feedback.value = ''; await load(filters()) }
onMounted(refresh)
async function changePage(next: number) { page.value = next; await refresh() }
async function download(format: 'pdf' | 'excel') {
  feedback.value = ''
  try {
    const full = await exportAll(filters())
    const name = `auditoria-${from.value}-${to.value}`
    if (format === 'excel') await exportExcel(full.logs.data.map(row => ({ Data: row.created_at, Responsável: row.user?.name ?? 'Usuário indisponível', Perfil: row.user_role_label ?? row.user_role ?? '—', Ação: row.action_label ?? row.action, Entidade: row.entity_label ?? row.entity ?? '—', Registro: row.entity_id ?? '—', Descrição: row.description, Alterações: JSON.stringify(row.metadata ?? {}) })), name)
    else { const current = data.value; data.value = full; await nextTick(); try { await exportPdf(reportRef.value, name) } finally { data.value = current } }
  } catch (cause) { feedback.value = `${cause instanceof Error ? cause.message : 'Falha na exportação.'} Reduza o período e tente novamente.` }
}
</script>

<template>
  <section ref="reportRef" class="audit-server"><div class="audit-head"><div><span class="eyebrow">Controle administrativo</span><h2>Auditoria</h2></div><div><button class="outline-button" :disabled="exportingExcel || loading" @click="download('excel')">Excel</button><button class="outline-button" :disabled="exportingPdf || loading" @click="download('pdf')">PDF</button></div></div>
    <form class="audit-filters" @submit.prevent="page = 1; refresh()"><ReportDatePicker v-model="from" label="De" /><ReportDatePicker v-model="to" label="Até" /><label>Entidade<select v-model="entity"><option value="">Todas</option><option v-for="value in ['occurrence','reservation','notice','user','unit','common_area']" :key="value" :value="value">{{ value }}</option></select></label><label>ID do registro<input v-model="entityId" inputmode="numeric" :disabled="!entity" /></label><label>ID do responsável<input v-model="userId" inputmode="numeric" /></label><label>Perfil<select v-model="role"><option value="">Todos</option><option v-for="value in ['admin','syndic','employee','resident']" :key="value" :value="value">{{ value }}</option></select></label><label>Ação<input v-model="action" placeholder="Código da ação" /></label><button type="submit" class="primary-button" :disabled="loading">Aplicar filtros</button></form>
    <p v-if="loading" role="status">Carregando auditoria...</p><p v-if="error || feedback" class="form-error" role="alert">{{ error || feedback }} <button v-if="error" class="outline-button" @click="refresh">Tentar novamente</button></p>
    <template v-if="data"><div class="audit-summary"><article class="panel"><small>Eventos no período</small><strong>{{ data.summary.total }}</strong></article><article v-for="item in data.by_role" :key="item.role" class="panel"><small>{{ item.label }}</small><strong>{{ item.total }}</strong></article></div><article class="panel audit-list"><h3>Eventos registrados</h3><div class="table-wrap"><table><thead><tr><th>Data</th><th>Responsável</th><th>Perfil na ação</th><th>Ação</th><th>Registro</th><th></th></tr></thead><tbody><tr v-for="row in data.logs.data" :key="row.id"><td>{{ new Date(row.created_at).toLocaleString('pt-BR') }}</td><td>{{ row.user?.name ?? 'Usuário indisponível' }}</td><td>{{ row.user_role_label ?? row.user_role ?? '—' }}</td><td>{{ row.action_label ?? row.action }}</td><td>{{ row.entity_label ?? row.entity }} #{{ row.entity_id }}</td><td><button class="outline-button" @click="selected = row">Detalhes</button></td></tr><tr v-if="!data.logs.data.length"><td colspan="6">Nenhum evento encontrado.</td></tr></tbody></table></div><nav v-if="data.logs.last_page > 1" class="pagination"><button class="outline-button" :disabled="page <= 1" @click="changePage(page - 1)">Anterior</button><span>{{ page }} de {{ data.logs.last_page }} · {{ data.logs.total }} registros</span><button class="outline-button" :disabled="page >= data.logs.last_page" @click="changePage(page + 1)">Próxima</button></nav></article></template>
    <DetailModal v-if="selected" title="Detalhes do evento" icon="alert" @close="selected = null"><dl class="detail-list"><div><dt>Descrição</dt><dd>{{ selected.description }}</dd></div><div><dt>Responsável</dt><dd>{{ selected.user?.name ?? 'Usuário indisponível' }}</dd></div><div><dt>Perfil na ação</dt><dd>{{ selected.user_role_label ?? selected.user_role ?? '—' }}</dd></div><div><dt>Data</dt><dd>{{ new Date(selected.created_at).toLocaleString('pt-BR') }}</dd></div></dl><h3>Alterações registradas</h3><div v-if="selected.metadata?.de || selected.metadata?.para" class="audit-change"><div><strong>Antes</strong><pre>{{ JSON.stringify(selected.metadata?.de ?? {}, null, 2) }}</pre></div><div><strong>Depois</strong><pre>{{ JSON.stringify(selected.metadata?.para ?? {}, null, 2) }}</pre></div></div><pre v-else>{{ JSON.stringify(selected.metadata ?? {}, null, 2) }}</pre></DetailModal>
  </section>
</template>

<style scoped>
.audit-server{color:var(--navy)}.audit-head,.audit-filters,.pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}.audit-head>div:last-child{display:flex;gap:8px}.audit-filters{justify-content:flex-start;margin:18px 0}.audit-filters label{display:grid;gap:5px}.audit-filters input,.audit-filters select{background:var(--input);color:var(--navy);border:1px solid var(--line);border-radius:8px;padding:8px}.audit-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:15px 0}.audit-summary article,.audit-list{padding:16px}.audit-summary small{display:block;color:var(--neutral)}.audit-summary strong{font-size:26px}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse}th,td{padding:10px;text-align:left;border-bottom:1px solid var(--line)}.pagination{justify-content:flex-end;margin-top:12px}pre{max-height:50vh;overflow:auto;background:var(--surface-raised);color:var(--navy);padding:12px;border-radius:8px;white-space:pre-wrap}@media print{.audit-filters,.audit-head button,.pagination{display:none}}
.audit-change{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}@media(max-width:600px){.audit-change{grid-template-columns:1fr}}
</style>
