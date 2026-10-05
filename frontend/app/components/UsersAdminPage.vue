<script setup lang="ts">
import type { CreateUserInput, UpdateUserInput, User, UserRole } from '~/domain/user'

const props = defineProps<{
  users: User[]
  loading?: boolean
  currentUserId?: string
  onCreate: (input: CreateUserInput) => Promise<unknown>
  onUpdate: (id: string, input: UpdateUserInput) => Promise<unknown>
  onDeactivate: (id: string) => Promise<unknown>
}>()

const roleLabels: Record<UserRole, string> = { resident: 'Morador', employee: 'Funcionário', syndic: 'Síndico', admin: 'Administrador' }
const search = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('active')
const editing = ref<User | null>(null)
const formOpen = ref(false)
const formError = ref('')
const saving = ref(false)
const form = reactive<{ name: string; email: string; role: UserRole; status: User['status'] }>({ name: '', email: '', role: 'resident', status: 'active' })

const filteredUsers = computed(() => props.users.filter(user =>
  (statusFilter.value === 'all' || user.status === statusFilter.value) &&
  `${user.name} ${user.email}`.toLocaleLowerCase().includes(search.value.toLocaleLowerCase().trim()),
))

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', email: '', role: 'resident', status: 'active' })
  formError.value = ''
  formOpen.value = true
}

function openEdit(user: User) {
  editing.value = user
  Object.assign(form, { name: user.name, email: user.email, role: user.role, status: user.status })
  formError.value = ''
  formOpen.value = true
}

async function submit() {
  if (!form.name.trim() || !form.email.trim()) { formError.value = 'Informe nome e e-mail.'; return }
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) await props.onUpdate(editing.value.id, { name: form.name.trim(), email: form.email.trim(), role: form.role, status: form.status })
    else await props.onCreate({ name: form.name.trim(), email: form.email.trim(), role: form.role, status: 'active' })
    formOpen.value = false
  } catch (cause) { formError.value = cause instanceof Error ? cause.message : 'Não foi possível salvar o usuário.' }
  finally { saving.value = false }
}

async function requestDeactivate(user: User) {
  if (user.id !== props.currentUserId && user.status === 'active' && confirm(`Inativar ${user.name}?`)) {
    try { await props.onDeactivate(user.id) }
    catch (cause) { formError.value = cause instanceof Error ? cause.message : 'Não foi possível inativar o usuário.' }
  }
}
</script>

<template>
  <section class="section-intro">
    <div class="section-icon"><SvgIcon name="users" /></div>
    <div><h2>Usuários</h2><p>Gerencie moradores, funcionários, síndicos e administradores.</p></div>
    <button class="primary-button" @click="openCreate">Novo usuário</button>
  </section>
  <article class="panel detail-panel users-admin-panel">
    <div class="users-toolbar">
      <input v-model="search" class="users-search" type="search" placeholder="Buscar por nome ou e-mail" aria-label="Buscar usuários" />
      <div class="filter-row users-filters">
        <button v-for="filter in [{ value: 'active', label: 'Ativos' }, { value: 'inactive', label: 'Inativos' }, { value: 'all', label: 'Todos' }]" :key="filter.value" class="filter" :class="{ selected: statusFilter === filter.value }" @click="statusFilter = filter.value as typeof statusFilter">{{ filter.label }}</button>
      </div>
    </div>
    <p v-if="formError && !formOpen" class="form-error">{{ formError }}</p>
    <div v-if="loading" class="empty-row">Carregando usuários...</div>
    <div v-else-if="!filteredUsers.length" class="empty-row">Nenhum usuário encontrado.</div>
    <div v-for="user in filteredUsers" v-else :key="user.id" class="detail-row user-row">
      <span class="avatar user-avatar">{{ user.name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase() }}</span>
      <span><strong>{{ user.name }}</strong><small>{{ user.email }}</small></span>
      <em :class="user.status === 'active' ? 'green' : 'gray'">{{ roleLabels[user.role] }} · {{ user.status === 'active' ? 'Ativo' : 'Inativo' }}</em>
      <button class="outline-button" @click="openEdit(user)">Editar</button>
      <button v-if="user.status === 'active'" class="outline-button danger-action" :disabled="user.id === currentUserId" @click="requestDeactivate(user)">Inativar</button>
    </div>
  </article>

  <div v-if="formOpen" class="modal-backdrop" @click.self="formOpen = false">
    <form class="modal glass" @submit.prevent="submit">
      <button type="button" class="modal-close" aria-label="Fechar" @click="formOpen = false"></button>
      <div class="section-icon"><SvgIcon name="users" /></div>
      <h2>{{ editing ? 'Editar usuário' : 'Novo usuário' }}</h2>
      <p>Defina os dados e o papel de acesso.</p>
      <label>Nome<input v-model="form.name" autocomplete="name" required /></label>
      <label>E-mail<input v-model="form.email" type="email" autocomplete="email" required /></label>
      <label>Papel<select v-model="form.role"><option v-for="(label, role) in roleLabels" :key="role" :value="role">{{ label }}</option></select></label>
      <label v-if="editing">Status<select v-model="form.status"><option value="active">Ativo</option><option value="inactive">Inativo</option></select></label>
      <p v-if="formError" class="form-error">{{ formError }}</p>
      <div class="modal-actions"><button type="button" class="outline-button" @click="formOpen = false">Cancelar</button><button type="submit" class="primary-button" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar usuário' }}</button></div>
    </form>
  </div>
</template>
