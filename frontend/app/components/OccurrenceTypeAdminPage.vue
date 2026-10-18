<script setup lang="ts">
import type { CreateOccurrenceTypeInput, OccurrenceType, UpdateOccurrenceTypeInput } from '~/domain/occurrence-type'

const props = defineProps<{ types: OccurrenceType[]; loading: boolean; error?: string; onCreate: (input: CreateOccurrenceTypeInput) => Promise<unknown>; onUpdate: (id: string, input: UpdateOccurrenceTypeInput) => Promise<unknown>; onRemove: (id: string) => Promise<unknown> }>()
const name = ref(''); const description = ref(''); const employeeCanClose = ref(false); const active = ref(true); const editing = ref<string | null>(null); const formOpen = ref(false); const feedback = ref(''); const saving = ref(false)
function reset() { editing.value = null; name.value = ''; description.value = ''; employeeCanClose.value = false; active.value = true }
function openCreate() { reset(); feedback.value = ''; formOpen.value = true }
function edit(item: OccurrenceType) { editing.value = item.id; name.value = item.name; description.value = item.description ?? ''; employeeCanClose.value = item.employeeCanClose; active.value = item.active; feedback.value = ''; formOpen.value = true }
function closeForm() { formOpen.value = false; reset() }
async function save() { if (!name.value.trim()) { feedback.value = 'Informe o nome do tipo.'; return }; saving.value = true; feedback.value = ''; try { const input = { name: name.value.trim(), description: description.value.trim() || undefined, employeeCanClose: employeeCanClose.value, active: active.value }; if (editing.value) await props.onUpdate(editing.value, input); else await props.onCreate(input); closeForm(); feedback.value = 'Tipo salvo com sucesso.' } catch (cause) { feedback.value = cause instanceof Error ? cause.message : 'Não foi possível salvar o tipo.' } finally { saving.value = false } }
async function remove(item: OccurrenceType) { if (!confirm(`Inativar o tipo “${item.name}”?`)) return; saving.value = true; feedback.value = ''; try { await props.onRemove(item.id); feedback.value = 'Tipo inativado com sucesso.' } catch (cause) { feedback.value = cause instanceof Error ? cause.message : 'Não foi possível inativar o tipo.' } finally { saving.value = false } }
</script>
<template>
  <section class="admin-shell">
    <div class="section-intro"><div class="section-icon"><SvgIcon name="alert" /></div><div><h2>Tipos de ocorrência</h2><p>Gerencie os tipos disponíveis para abertura de ocorrências.</p></div><button class="primary-button" type="button" @click="openCreate">Novo tipo</button></div>
    <p v-if="feedback || error" class="form-error" role="status">{{ feedback || error }}</p>
    <article class="panel detail-panel"><div v-if="loading" class="empty-row">Carregando tipos...</div><div v-else-if="!types.length" class="empty-row">Nenhum tipo cadastrado.</div><div v-for="item in types" v-else :key="item.id" class="detail-row"><span class="row-icon"><SvgIcon name="alert" /></span><span><strong>{{ item.name }}</strong><small>{{ item.description || 'Sem descrição' }}</small></span><em :class="item.active ? 'green' : 'gray'">{{ item.active ? 'Ativo' : 'Inativo' }}</em><button type="button" class="outline-button" @click="edit(item)">Editar</button><button type="button" class="outline-button danger-action" :disabled="!item.active || saving" @click="remove(item)">Inativar</button></div></article>

    <div v-if="formOpen" class="modal-backdrop" @click.self="closeForm">
      <form class="modal glass" aria-labelledby="occurrence-type-form-title" @submit.prevent="save">
        <button type="button" class="modal-close" aria-label="Fechar" @click="closeForm"></button>
        <div class="section-icon"><SvgIcon name="alert" /></div>
        <h2 id="occurrence-type-form-title">{{ editing ? 'Editar tipo' : 'Novo tipo' }}</h2>
        <p>Defina como a ocorrência será classificada e atendida.</p>
        <label>Nome<input v-model="name" maxlength="60" autocomplete="off" required /></label>
        <label>Descrição<textarea v-model="description" maxlength="255" /></label>
        <label class="checkbox-field"><input v-model="employeeCanClose" type="checkbox" /> Funcionário pode finalizar este tipo</label>
        <label class="checkbox-field"><input v-model="active" type="checkbox" /> Tipo ativo</label>
        <p v-if="feedback || error" class="form-error" role="alert">{{ feedback || error }}</p>
        <div class="modal-actions"><button type="button" class="outline-button" @click="closeForm">Cancelar</button><button type="submit" class="primary-button" :disabled="saving">{{ saving ? 'Salvando...' : editing ? 'Salvar alterações' : 'Criar tipo' }}</button></div>
      </form>
    </div>
  </section>
</template>

<style scoped>
.checkbox-field{display:flex;align-items:center;gap:8px}.checkbox-field input{width:16px;height:16px;margin:0;accent-color:var(--primary)}
</style>
