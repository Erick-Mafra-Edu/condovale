<script setup lang="ts">
import type { Unit, User } from '~/domain/user'

type Occupancy = {
  id: string
  user_id: string
  unit_id: string
  occupant_type: 'owner' | 'tenant' | 'dependent' | string
  started_at: string
  ended_at?: string | null
  is_active: boolean
}

const props = defineProps<{
  occupancies: Occupancy[]
  selectedOccupancy?: Occupancy | null
  users: User[]
  units: Unit[]
  loading?: boolean
  error?: { message?: string } | null
}>()
const emit = defineEmits<{
  create: [input: { user_id: string; unit_id: string; occupant_type: 'owner' | 'tenant' | 'dependent'; started_at?: string }]
  delete: [id: string]
  refresh: []
  openDetail: [id: string]
  closeDetail: []
}>()

const showForm = ref(false)
const userId = ref('')
const unitId = ref('')
const occupantType = ref<'owner' | 'tenant' | 'dependent'>('tenant')
const startedAt = ref(new Date().toISOString().slice(0, 10))
const feedback = ref('')
const userById = computed(() => new Map(props.users.map(user => [user.id, user])))
const unitById = computed(() => new Map(props.units.map(unit => [unit.id, unit])))
const activeResidents = computed(() => props.users.filter(user => user.role === 'resident' && user.status === 'active'))
const availableResidents = computed(() => activeResidents.value.filter(user => !props.occupancies.some(item => item.user_id === user.id && item.is_active)))
const activeUnits = computed(() => props.units.filter(unit => unit.status === 'active'))
const residentOptions = computed(() => availableResidents.value.map(user => ({ label: user.name, value: user.id })))
const unitOptions = computed(() => activeUnits.value.map(unit => ({ label: unitName(unit.id), value: unit.id })))
const occupantTypeOptions = [
  { label: 'Proprietário', value: 'owner' },
  { label: 'Inquilino', value: 'tenant' },
  { label: 'Dependente', value: 'dependent' }
]
const selectUi = { content: 'occupancy-select-menu', item: 'occupancy-select-option' }
const activeOccupancies = computed(() => props.occupancies.filter(item => item.is_active))
const selected = computed(() => props.selectedOccupancy ?? null)
const userName = (id: string) => userById.value.get(id)?.name ?? `Morador #${id}`
const unitName = (id: string) => { const unit = unitById.value.get(id); return unit ? `${unit.block ? `${unit.block} · ` : ''}${unit.number}` : `Unidade #${id}` }
const typeLabel = (type: string) => ({ owner: 'Proprietário', tenant: 'Inquilino', dependent: 'Dependente' }[type] ?? type)
const formatDate = (value?: string | null) => value ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${value.slice(0, 10)}T12:00:00`)) : '—'

function resetForm() {
  userId.value = availableResidents.value[0]?.id ?? ''
  unitId.value = activeUnits.value[0]?.id ?? ''
  occupantType.value = 'tenant'
  startedAt.value = new Date().toISOString().slice(0, 10)
  feedback.value = ''
}
function openForm() { resetForm(); showForm.value = true }
function submit() {
  if (!userId.value || !unitId.value || !startedAt.value) { feedback.value = 'Informe morador, unidade e data de início.'; return }
  emit('create', { user_id: userId.value, unit_id: unitId.value, occupant_type: occupantType.value, started_at: startedAt.value })
  showForm.value = false
}
function closeDetails() { emit('closeDetail') }
</script>

<template>
  <section class="occupancy-page">
    <div class="occupancy-heading"><div><span class="eyebrow">Administração · acesso restrito</span><h2>Moradores e unidades</h2><p>Gerencie os vínculos atuais e preserve o histórico de ocupação.</p></div><div class="occupancy-actions"><button class="outline-button" :disabled="loading" @click="emit('refresh')">Atualizar</button><button class="primary-button" @click="openForm">＋ Novo vínculo</button></div></div>
    <p v-if="error" class="occupancy-feedback error" role="alert">{{ error.message ?? 'Não foi possível carregar os vínculos.' }}</p>
    <article v-if="!loading && !activeOccupancies.length" class="panel occupancy-empty"><div class="empty-icon">⌂</div><h3>Nenhum vínculo ativo</h3><p>Cadastre o primeiro vínculo entre um morador e uma unidade.</p><button class="primary-button" @click="openForm">Criar vínculo</button></article>
    <div v-else class="occupancy-list" aria-live="polite"><article v-for="item in activeOccupancies" :key="item.id" class="panel occupancy-card"><div class="occupancy-icon"><SvgIcon name="users" /></div><div class="occupancy-main"><div class="occupancy-title"><h3>{{ userName(item.user_id) }}</h3><span class="occupancy-badge">{{ typeLabel(item.occupant_type) }}</span></div><p>{{ unitName(item.unit_id) }} · desde {{ formatDate(item.started_at) }}</p></div><div class="occupancy-card-actions"><button class="outline-button" @click="emit('openDetail', item.id)">Ver detalhes</button><button class="text-button danger" @click="emit('delete', item.id)">Encerrar</button></div></article><div v-if="loading" class="panel occupancy-empty"><p>Carregando vínculos…</p></div></div>
    <div v-if="showForm" class="modal-backdrop" @click.self="showForm = false"><form class="modal glass occupancy-modal" @submit.prevent="submit"><button type="button" class="modal-close" aria-label="Fechar" @click="showForm = false"></button><div class="section-icon"><SvgIcon name="users" /></div><h2>Novo vínculo</h2><p>Associe um morador ativo a uma unidade.</p><label for="occupancy-resident">Morador</label><USelect id="occupancy-resident" v-model="userId" class="occupancy-select" :items="residentOptions" :ui="selectUi" :placeholder="residentOptions.length ? 'Selecione um morador' : 'Nenhum morador disponível'" :disabled="!residentOptions.length" required /><label for="occupancy-unit">Unidade</label><USelect id="occupancy-unit" v-model="unitId" class="occupancy-select" :items="unitOptions" :ui="selectUi" :placeholder="unitOptions.length ? 'Selecione uma unidade' : 'Nenhuma unidade disponível'" :disabled="!unitOptions.length" required /><label for="occupancy-type">Tipo de ocupação</label><USelect id="occupancy-type" v-model="occupantType" class="occupancy-select" :items="occupantTypeOptions" :ui="selectUi" /><label>Início<input v-model="startedAt" type="date" required /></label><p v-if="feedback" class="occupancy-feedback error">{{ feedback }}</p><div class="modal-actions"><button type="button" class="outline-button" @click="showForm = false">Cancelar</button><button class="primary-button" type="submit">Criar vínculo</button></div></form></div>
    <DetailModal v-if="selected" title="Detalhes do vínculo" icon="alert" @close="closeDetails"><dl class="detail-list"><div><dt>Morador</dt><dd>{{ userName(selected.user_id) }}</dd></div><div><dt>Unidade</dt><dd>{{ unitName(selected.unit_id) }}</dd></div><div><dt>Tipo</dt><dd>{{ typeLabel(selected.occupant_type) }}</dd></div><div><dt>Início</dt><dd>{{ formatDate(selected.started_at) }}</dd></div><div v-if="selected.ended_at"><dt>Encerramento</dt><dd>{{ formatDate(selected.ended_at) }}</dd></div><div><dt>Status</dt><dd><em class="green">{{ selected.is_active ? 'Ativo' : 'Encerrado' }}</em></dd></div></dl><div class="reservation-review-actions"><button class="outline-button danger-action" :disabled="!selected.is_active" @click="emit('delete', selected.id); closeDetails()">Encerrar vínculo</button></div></DetailModal>
  </section>
</template>

<style scoped>
.occupancy-page{max-width:1180px;margin:0 auto;padding-bottom:40px;color:#102a43}.occupancy-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:24px}.occupancy-heading h2{margin:8px 0 5px;font-size:30px;letter-spacing:-.04em}.occupancy-heading p{margin:0;color:#64748b;font-size:14px}.occupancy-actions{display:flex;gap:10px}.occupancy-actions button{min-height:42px}.occupancy-list{display:grid;gap:12px}.occupancy-card{display:flex;align-items:center;gap:16px;padding:17px 19px;min-height:94px}.occupancy-icon{display:grid;place-items:center;width:50px;height:50px;border-radius:15px;background:#ddf6f7;color:#006a78;flex:none}.occupancy-icon .svg-icon{width:22px;height:22px}.occupancy-main{flex:1;min-width:0}.occupancy-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.occupancy-title h3{margin:0;font-size:16px}.occupancy-main p{margin:7px 0 0;color:#64748b;font-size:12px}.occupancy-badge{padding:5px 9px;border-radius:999px;background:#edf5f5;color:#006a78;font-size:10px;font-weight:800}.occupancy-card-actions{display:flex;align-items:center;gap:12px}.occupancy-card-actions .outline-button{min-height:38px}.text-button{border:0;background:transparent;padding:8px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}.text-button.danger{color:#a5383d}.occupancy-empty{display:grid;place-items:center;text-align:center;padding:62px 24px}.occupancy-empty h3{margin:18px 0 6px;font-size:20px}.occupancy-empty p{margin:0 0 20px;color:#64748b;font-size:13px}.empty-icon{display:grid;place-items:center;width:56px;height:56px;border-radius:17px;background:#ddf6f7;color:#006a78;font-size:26px}.occupancy-feedback{margin:0 0 16px;padding:10px 12px;border-radius:9px;font-size:12px;font-weight:700}.occupancy-feedback.error{background:#fff1f2;color:#991b1b}.occupancy-modal{position:relative}.occupancy-modal label{display:grid;gap:3px;margin-top:14px;font-size:11px;font-weight:800}.occupancy-modal input,.occupancy-modal :deep(.occupancy-select){font:inherit;width:100%;margin-top:4px;border:1px solid var(--line);border-radius:9px;padding:11px;background:#fbfefe;color:var(--navy);font-size:11px;text-align:left}.occupancy-modal .modal-actions{margin-top:20px}.detail-list em{font-style:normal;border-radius:12px;padding:4px 7px;font-size:9px}.green{background:#eaf8ef;color:#166534}@media(max-width:700px){.occupancy-heading{display:block}.occupancy-actions{margin-top:18px}.occupancy-card{align-items:flex-start;flex-wrap:wrap}.occupancy-main{min-width:calc(100% - 66px)}.occupancy-card-actions{width:100%;padding-left:66px}.occupancy-card-actions .outline-button{flex:1}.occupancy-card-actions .text-button{white-space:nowrap}}@media(max-width:480px){.occupancy-card-actions{padding-left:0}.occupancy-card-actions .outline-button{width:100%}.occupancy-card-actions{display:grid;grid-template-columns:1fr}.occupancy-actions{display:grid;grid-template-columns:1fr 1fr}}
@media(prefers-color-scheme:dark){.occupancy-page{color:var(--navy)}.occupancy-heading p,.occupancy-main p,.occupancy-empty p{color:var(--neutral)}.occupancy-icon,.empty-icon{background:#123943;color:#35c2ca}.occupancy-badge{background:#143845;color:#bdd3da}.text-button.danger{color:#ffb6bc}.occupancy-feedback.error{background:#492b30;color:#ffadb3}.occupancy-modal input,.occupancy-modal :deep(.occupancy-select){background:#0a2935;border-color:#285263;color:#f1f7f8}.green{background:#164433;color:#83e5bb}}
</style>

<style>
.occupancy-select-menu{z-index:1001;width:var(--reka-select-trigger-width);max-height:240px;overflow:auto;border:1px solid var(--line);border-radius:9px;padding:4px;background:#fbfefe;color:var(--navy);box-shadow:0 12px 30px rgba(15,23,42,.18)}.occupancy-select-option{padding:9px;border-radius:7px;font-size:11px;font-weight:700}.occupancy-select-option[data-highlighted]{background:#e7f7f8}@media(prefers-color-scheme:dark){.occupancy-select-menu{background:#0a2935;border-color:#285263;color:#f1f7f8;box-shadow:0 14px 34px rgba(0,0,0,.38)}.occupancy-select-option[data-highlighted]{background:#123e4a}}
</style>
