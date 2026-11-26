<script setup lang="ts">
import type { CommonArea, CommonAreaInput } from '~/domain/reservation'
import { AppError } from '~/domain/app-error'

const props = defineProps<{
  areas: CommonArea[]; loading: boolean; error?: string | null
  onCreate: (input: CommonAreaInput) => Promise<{ message: string | null }>
  onUpdate: (id: string, input: Partial<CommonAreaInput>) => Promise<{ message: string | null }>
  onRemove: (id: string) => Promise<unknown>
  onReload: () => Promise<unknown>
}>()
const search = ref('')
const open = ref(false)
const editing = ref<string | null>(null)
const saving = ref(false)
const feedback = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive<CommonAreaInput>({ name: '', description: '', imageUrl: '', capacity: 1, openingTime: '08:00', closingTime: '22:00', maxReservationMinutes: 120, requiresApproval: true, status: 'available' })
const filtered = computed(() => props.areas.filter(area => area.name.toLocaleLowerCase().includes(search.value.toLocaleLowerCase())))
function edit(area?: CommonArea) {
  editing.value = area?.id ?? null
  Object.assign(form, { name: area?.name ?? '', description: area?.description ?? '', imageUrl: area?.imageUrl ?? '', capacity: area?.capacity ?? 1, openingTime: area?.openingTime ?? '08:00', closingTime: area?.closingTime ?? '22:00', maxReservationMinutes: area?.maxReservationMinutes ?? 120, requiresApproval: area?.requiresApproval ?? true, status: area?.status ?? 'available' })
  feedback.value = ''; fieldErrors.value = {}; open.value = true
}
async function save() {
  if (saving.value) return
  saving.value = true; feedback.value = ''; fieldErrors.value = {}
  try {
    const result = editing.value ? await props.onUpdate(editing.value, { ...form }) : await props.onCreate({ ...form })
    open.value = false; await props.onReload(); feedback.value = result.message ?? 'Área comum salva.'
  } catch (cause) { fieldErrors.value = cause instanceof AppError ? cause.fields ?? {} : {}; feedback.value = cause instanceof Error ? cause.message : 'Não foi possível salvar a área.' }
  finally { saving.value = false }
}
async function remove(area: CommonArea) {
  if (saving.value || !confirm(`Excluir a área “${area.name}”?`)) return
  saving.value = true; feedback.value = ''
  try { await props.onRemove(area.id); await props.onReload(); feedback.value = 'Área comum excluída.' }
  catch (cause) { feedback.value = cause instanceof Error ? cause.message : 'Não foi possível excluir a área.' }
  finally { saving.value = false }
}
</script>

<template>
  <section class="admin-shell"><div class="section-intro"><div class="section-icon"><SvgIcon name="building" /></div><div><h2>Áreas comuns</h2><p>Espaços e regras de reserva.</p></div><button class="primary-button" @click="edit()">Nova área</button></div>
    <label class="area-search">Pesquisar área<input v-model="search" type="search" placeholder="Nome da área" /></label>
    <p v-if="feedback" role="status">{{ feedback }}</p><p v-if="error" class="form-error" role="alert">{{ error }} <button class="outline-button" @click="onReload">Tentar novamente</button></p>
    <div v-if="loading" role="status">Carregando áreas...</div><div v-else-if="!filtered.length" class="panel empty-row">Nenhuma área encontrada.</div>
    <div v-else class="area-list"><article v-for="area in filtered" :key="area.id" class="panel area-row"><div><strong>{{ area.name }}</strong><p>{{ area.description }}</p><small>{{ area.openingTime }}–{{ area.closingTime }} · {{ area.maxReservationMinutes }} min · {{ area.requiresApproval ? 'Exige aprovação' : 'Aprovação automática' }} · {{ area.status === 'available' ? 'Disponível' : 'Indisponível' }}</small></div><div class="area-actions"><button class="outline-button" @click="edit(area)">Editar</button><button class="outline-button danger-action" :disabled="saving" @click="remove(area)">Excluir</button></div></article></div>
    <div v-if="open" class="modal-backdrop" @click.self="open = false"><form class="modal glass area-form" @submit.prevent="save"><button type="button" class="modal-close" aria-label="Fechar" @click="open = false"></button><h2>{{ editing ? 'Editar área comum' : 'Nova área comum' }}</h2><label>Nome<input v-model="form.name" maxlength="120" required /></label><label>Descrição<textarea v-model="form.description" maxlength="255" /></label><label>URL da imagem<input v-model="form.imageUrl" type="url" maxlength="255" /></label><label>Capacidade<input v-model.number="form.capacity" type="number" min="1" /></label><label>Abertura<input v-model="form.openingTime" type="time" required /></label><label>Fechamento (HH:MM ou 24:00)<input v-model="form.closingTime" type="text" pattern="([01][0-9]|2[0-3]):[0-5][0-9]|24:00" required /></label><label>Duração máxima em minutos<input v-model.number="form.maxReservationMinutes" type="number" min="30" step="30" /></label><label><input v-model="form.requiresApproval" type="checkbox" /> Exige aprovação</label><label>Situação<select v-model="form.status"><option value="available">Disponível</option><option value="unavailable">Indisponível</option></select></label><p v-if="feedback" class="form-error" role="alert">{{ feedback }}</p><p v-for="(messages, field) in fieldErrors" :key="field" class="form-error" role="alert">{{ field }}: {{ messages.join(' ') }}</p><div class="modal-actions"><button type="button" class="outline-button" @click="open = false">Cancelar</button><button type="submit" class="primary-button" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar' }}</button></div></form></div>
  </section>
</template>

<style scoped>
.area-search{display:grid;gap:5px;margin:16px 0;color:var(--navy)}.area-search input,.area-form input,.area-form textarea,.area-form select{background:var(--input);color:var(--navy);border:1px solid var(--line);border-radius:8px;padding:9px}.area-list{display:grid;gap:10px}.area-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px}.area-row p,.area-row small{color:var(--neutral)}.area-actions{display:flex;gap:8px}.area-form{max-height:90vh;overflow:auto}.area-form label{display:grid;gap:4px}.area-form input[type=checkbox]{width:auto;justify-self:start}@media(max-width:600px){.area-row{display:block}.area-actions{margin-top:12px}}
</style>
