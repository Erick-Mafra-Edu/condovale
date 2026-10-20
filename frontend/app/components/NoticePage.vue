<script setup lang="ts">
import { getPublishedNotices, type CreateNoticeInput, type Notice } from '~/domain/notice'

const props = defineProps<{
  notices: Notice[]
  loading: boolean
  error?: string | null
  canPublish: boolean
  authorId: string
  onCreate: (input: CreateNoticeInput) => Promise<unknown>
}>()

const title = ref('')
const content = ref('')
const formOpen = ref(false)
const feedback = ref('')
const saving = ref(false)

const publishedNotices = computed(() => getPublishedNotices(props.notices))

function reset() {
  title.value = ''
  content.value = ''
  feedback.value = ''
}

function openForm() {
  reset()
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
  reset()
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium' }).format(new Date(value))
}

async function publish() {
  if (!title.value.trim()) {
    feedback.value = 'Informe o título do comunicado.'
    return
  }
  if (!content.value.trim()) {
    feedback.value = 'Informe o conteúdo do comunicado.'
    return
  }

  saving.value = true
  feedback.value = ''
  try {
    await props.onCreate({
      title: title.value.trim(),
      content: content.value.trim(),
      authorId: props.authorId,
      publishedAt: new Date().toISOString(),
      status: 'published',
    })
    closeForm()
    feedback.value = 'Comunicado publicado com sucesso.'
  } catch (cause) {
    feedback.value = cause instanceof Error ? cause.message : 'Não foi possível publicar o comunicado.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="admin-shell">
    <div class="section-intro">
      <div class="section-icon"><SvgIcon name="message" /></div>
      <div><h2>Comunicados</h2><p>Informações importantes para os moradores.</p></div>
      <button v-if="canPublish" class="primary-button" type="button" @click="openForm">Publicar aviso</button>
    </div>

    <p v-if="feedback || error" class="form-error" role="status">{{ feedback || error }}</p>
    <article class="panel detail-panel" aria-live="polite">
      <div v-if="loading" class="empty-row">Carregando comunicados...</div>
      <div v-else-if="!publishedNotices.length" class="empty-row">Nenhum comunicado publicado.</div>
      <article v-for="notice in publishedNotices" v-else :key="notice.id" class="detail-row notice-row">
        <span class="row-icon"><SvgIcon name="message" /></span>
        <span><strong>{{ notice.title }}</strong><small>{{ formatDate(notice.publishedAt) }}</small><p>{{ notice.content }}</p></span>
      </article>
    </article>

    <div v-if="formOpen" class="modal-backdrop" @click.self="closeForm">
      <form class="modal glass" aria-labelledby="notice-form-title" @submit.prevent="publish">
        <button type="button" class="modal-close" aria-label="Fechar" @click="closeForm"></button>
        <div class="section-icon"><SvgIcon name="message" /></div>
        <h2 id="notice-form-title">Publicar aviso</h2>
        <p>O aviso ficará disponível imediatamente para os moradores.</p>
        <label>Título<input v-model="title" maxlength="120" autocomplete="off" required /></label>
        <label>Conteúdo<textarea v-model="content" maxlength="2000" rows="6" required /></label>
        <p v-if="feedback" class="form-error" role="alert">{{ feedback }}</p>
        <div class="modal-actions"><button type="button" class="outline-button" @click="closeForm">Cancelar</button><button type="submit" class="primary-button" :disabled="saving">{{ saving ? 'Publicando...' : 'Publicar aviso' }}</button></div>
      </form>
    </div>
  </section>
</template>

<style scoped>
.notice-row{align-items:flex-start}.notice-row p{margin:7px 0 0;color:#36566d;line-height:1.5}.notice-row small{display:block;margin-top:4px}
@media(prefers-color-scheme:dark){.notice-row p{color:#b9d1d8}}
</style>
