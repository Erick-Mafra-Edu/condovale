<script setup lang="ts">
import { getPublishedNotices, type CreateNoticeInput, type Notice, type UpdateNoticeInput } from '~/domain/notice'
import { AppError } from '~/domain/app-error'

const props = defineProps<{
  notices: Notice[]; total: number; loading: boolean; error?: string | null; canPublish: boolean
  onLoad: (page: number, status?: Notice['status']) => Promise<unknown>
  onCreate: (input: CreateNoticeInput) => Promise<unknown>
  onUpdate: (id: string, input: UpdateNoticeInput) => Promise<unknown>
  onPublish: (id: string) => Promise<unknown>
  onRemove: (id: string) => Promise<unknown>
}>()
const page = ref(1)
const tab = ref<'published' | 'draft'>('published')
const formOpen = ref(false)
const editingId = ref<string | null>(null)
const title = ref('')
const content = ref('')
const saving = ref(false)
const feedback = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const items = computed(() => tab.value === 'published' ? getPublishedNotices(props.notices) : props.notices.filter(item => item.status === 'draft'))
const maxPage = computed(() => Math.max(1, Math.ceil(props.total / 10)))
async function load(next = page.value) { page.value = next; await props.onLoad(page.value, props.canPublish ? tab.value : undefined) }
async function switchTab(next: 'published' | 'draft') { tab.value = next; await load(1) }
function openForm(notice?: Notice) { editingId.value = notice?.id ?? null; title.value = notice?.title ?? ''; content.value = notice?.content ?? ''; feedback.value = ''; fieldErrors.value = {}; formOpen.value = true }
function closeForm() { formOpen.value = false; editingId.value = null }
async function save(status: 'draft' | 'published') {
  if (saving.value) return
  saving.value = true; feedback.value = ''; fieldErrors.value = {}
  try {
    if (editingId.value) { await props.onUpdate(editingId.value, { title: title.value.trim(), content: content.value.trim() }); if (status === 'published' && tab.value === 'draft') await props.onPublish(editingId.value) }
    else await props.onCreate({ title: title.value.trim(), content: content.value.trim(), status })
    closeForm(); await load(1); feedback.value = status === 'draft' ? 'Rascunho salvo.' : 'Comunicado publicado.'
  } catch (cause) { fieldErrors.value = cause instanceof AppError ? cause.fields ?? {} : {}; feedback.value = cause instanceof Error ? cause.message : 'Não foi possível salvar o comunicado.' }
  finally { saving.value = false }
}
async function publish(notice: Notice) {
  if (saving.value || !confirm(`Publicar “${notice.title}”?`)) return
  saving.value = true
  try { await props.onPublish(notice.id); await load(1); feedback.value = 'Comunicado publicado.' }
  catch (cause) { feedback.value = cause instanceof Error ? cause.message : 'Não foi possível publicar.' }
  finally { saving.value = false }
}
async function remove(notice: Notice) {
  if (saving.value || !confirm(`Excluir “${notice.title}”?`)) return
  saving.value = true
  try { await props.onRemove(notice.id); await load(1); feedback.value = 'Comunicado excluído.' }
  catch (cause) { feedback.value = cause instanceof Error ? cause.message : 'Não foi possível excluir.' }
  finally { saving.value = false }
}
function formatDate(value: string | null) { return value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value)) : 'Não publicado' }
</script>

<template>
  <section class="admin-shell">
    <div class="section-intro"><div class="section-icon"><SvgIcon name="message" /></div><div><h2>Comunicados</h2><p>Informações importantes para os moradores.</p></div><button v-if="canPublish" class="primary-button" type="button" @click="openForm()">Novo comunicado</button></div>
    <nav v-if="canPublish" class="notice-tabs" aria-label="Situação dos comunicados"><button :aria-current="tab === 'published'" @click="switchTab('published')">Mural</button><button :aria-current="tab === 'draft'" @click="switchTab('draft')">Rascunhos</button></nav>
    <p v-if="feedback" role="status">{{ feedback }}</p><p v-if="error" class="form-error" role="alert">{{ error }} <button class="outline-button" @click="load()">Tentar novamente</button></p>
    <article class="panel detail-panel" aria-live="polite"><div v-if="loading" class="empty-row">Carregando comunicados...</div><div v-else-if="!items.length" class="empty-row">{{ tab === 'draft' ? 'Nenhum rascunho.' : 'Nenhum comunicado publicado.' }}</div><article v-for="notice in items" v-else :key="notice.id" class="detail-row notice-row"><span class="row-icon"><SvgIcon name="message" /></span><span><strong>{{ notice.title }}</strong><small>{{ notice.authorName ?? 'Autor indisponível' }} · {{ formatDate(notice.publishedAt) }}</small><p>{{ notice.content }}</p><span v-if="canPublish" class="notice-actions"><button class="outline-button" @click="openForm(notice)">Editar</button><button v-if="notice.status === 'draft'" class="outline-button" :disabled="saving" @click="publish(notice)">Publicar</button><button class="outline-button danger-action" :disabled="saving" @click="remove(notice)">Excluir</button></span></span></article></article>
    <nav v-if="maxPage > 1" class="notice-pagination" aria-label="Páginas dos comunicados"><button class="outline-button" :disabled="page <= 1 || loading" @click="load(page - 1)">Anterior</button><span>Página {{ page }} de {{ maxPage }}</span><button class="outline-button" :disabled="page >= maxPage || loading" @click="load(page + 1)">Próxima</button></nav>
    <div v-if="formOpen" class="modal-backdrop" @click.self="closeForm"><form class="modal glass" aria-labelledby="notice-form-title" @submit.prevent="save('published')"><button type="button" class="modal-close" aria-label="Fechar" @click="closeForm"></button><h2 id="notice-form-title">{{ editingId ? 'Editar comunicado' : 'Novo comunicado' }}</h2><label>Título<input v-model="title" maxlength="120" required /></label><label>Conteúdo<textarea v-model="content" maxlength="5000" rows="6" required /></label><p v-if="feedback" class="form-error" role="alert">{{ feedback }}</p><p v-for="(messages, field) in fieldErrors" :key="field" class="form-error" role="alert">{{ field }}: {{ messages.join(' ') }}</p><div class="modal-actions"><button type="button" class="outline-button" @click="closeForm">Cancelar</button><button v-if="!editingId || tab === 'draft'" type="button" class="outline-button" :disabled="saving || !title.trim() || !content.trim()" @click="save('draft')">Salvar rascunho</button><button type="submit" class="primary-button" :disabled="saving">{{ saving ? 'Salvando...' : editingId && tab === 'draft' ? 'Salvar e publicar' : 'Publicar' }}</button></div></form></div>
  </section>
</template>

<style scoped>
.notice-row{align-items:flex-start}.notice-row p{margin:7px 0;color:var(--neutral);line-height:1.5}.notice-row small{display:block;margin-top:4px;color:var(--neutral)}.notice-actions,.notice-tabs,.notice-pagination{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px}.notice-tabs button{border:1px solid var(--line);background:var(--surface);color:var(--navy);border-radius:8px;padding:8px 14px;cursor:pointer}.notice-tabs button[aria-current=true]{background:var(--selected);border-color:var(--primary)}.notice-pagination{justify-content:center}
</style>
