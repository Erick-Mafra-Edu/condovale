<script setup lang="ts">
import type { CreateUserInput, UpdateUserInput, User, UserCreationResult, UserRole } from '~/domain/user'
import type { PermissionCatalogItem } from '~/domain/permission'

const { catalog: permissionCatalog, selected: permissionSummary, loading: permissionsLoading, error: permissionsError, loadCatalog, loadUserPermissions, grant, revoke } = usePermissions()

const props = defineProps<{
  users: User[]
  loading?: boolean
  currentUserId?: string
  onCreate: (input: CreateUserInput) => Promise<UserCreationResult>
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
const creationResult = ref<UserCreationResult | null>(null)
const copiedPassword = ref(false)
const copyError = ref('')
const permissionUser = ref<User | null>(null)
const permissionMessage = ref('')
const permissionSaving = ref(false)
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

const permissionLabel = (permission: string) => permission.replaceAll('-', ' ').replace(/(^| )\S/g, value => value.toUpperCase())
const hasDirectPermission = (permission: PermissionCatalogItem['name']) => permissionSummary.value?.direct.includes(permission) ?? false
const hasRolePermission = (permission: PermissionCatalogItem['name']) => permissionSummary.value?.fromRole.includes(permission) ?? false

async function openPermissions(user: User) {
  permissionUser.value = user
  permissionMessage.value = ''
  await Promise.all([loadCatalog(), loadUserPermissions(user.id)])
}

async function grantPermission(permission: PermissionCatalogItem['name']) {
  if (!permissionUser.value || hasRolePermission(permission)) return
  permissionSaving.value = true
  permissionMessage.value = ''
  try {
    await grant(permissionUser.value.id, permission)
    permissionMessage.value = 'Permissão concedida.'
  } catch { /* composable exposes the API error in permissionsError */ }
  finally { permissionSaving.value = false }
}

async function revokePermission(permission: PermissionCatalogItem['name']) {
  if (!permissionUser.value || !hasDirectPermission(permission) || !confirm(`Revogar ${permissionLabel(permission)} de ${permissionUser.value.name}?`)) return
  permissionSaving.value = true
  permissionMessage.value = ''
  try {
    await revoke(permissionUser.value.id, permission)
    permissionMessage.value = 'Permissão revogada.'
  } catch { /* composable exposes the API error in permissionsError */ }
  finally { permissionSaving.value = false }
}

async function submit() {
  if (!form.name.trim() || !form.email.trim()) { formError.value = 'Informe nome e e-mail.'; return }
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) await props.onUpdate(editing.value.id, { name: form.name.trim(), email: form.email.trim(), role: form.role, status: form.status })
    else creationResult.value = await props.onCreate({ name: form.name.trim(), email: form.email.trim(), role: form.role, status: 'active' })
    formOpen.value = false
  } catch (cause) { formError.value = cause instanceof Error ? cause.message : 'Não foi possível salvar o usuário.' }
  finally { saving.value = false }
}

function closeCreationResult() {
  creationResult.value = null
  copiedPassword.value = false
  copyError.value = ''
}

async function copyTemporaryPassword() {
  if (!creationResult.value) return
  try {
    await navigator.clipboard.writeText(creationResult.value.temporaryPassword)
    copiedPassword.value = true
    copyError.value = ''
  } catch {
    copyError.value = 'Não foi possível copiar automaticamente. Selecione e copie a senha manualmente.'
  }
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
      <button class="outline-button" @click="openPermissions(user)">Permissões</button>
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

  <div v-if="creationResult" class="modal-backdrop" @click.self="closeCreationResult">
    <section class="modal glass" role="dialog" aria-modal="true" aria-labelledby="temporary-password-title">
      <button type="button" class="modal-close" aria-label="Fechar senha temporária" @click="closeCreationResult"></button>
      <div class="section-icon"><SvgIcon name="users" /></div>
      <h2 id="temporary-password-title">Usuário criado</h2>
      <p>Repasse esta senha temporária a {{ creationResult.user.name }}. Ela não será exibida novamente.</p>
      <label>E-mail<input :value="creationResult.user.email" readonly /></label>
      <label>Senha temporária<input :value="creationResult.temporaryPassword" readonly aria-describedby="temporary-password-help" /></label>
      <p id="temporary-password-help" class="temporary-password-note">A pessoa deverá trocar a senha no primeiro acesso.</p>
      <p v-if="copyError" class="form-error" role="alert">{{ copyError }}</p>
      <div class="modal-actions"><button class="outline-button" type="button" @click="closeCreationResult">Fechar</button><button class="primary-button" type="button" @click="copyTemporaryPassword">{{ copiedPassword ? 'Senha copiada' : 'Copiar senha' }}</button></div>
    </section>
  </div>

  <div v-if="permissionUser" class="modal-backdrop" @click.self="permissionUser = null">
    <section class="modal glass" role="dialog" aria-modal="true" aria-labelledby="permissions-title">
      <button type="button" class="modal-close" aria-label="Fechar permissões" @click="permissionUser = null"></button>
      <div class="section-icon"><SvgIcon name="users" /></div>
      <h2 id="permissions-title">Permissões de {{ permissionUser.name }}</h2>
      <p>As permissões herdadas do papel não podem ser revogadas aqui.</p>
      <div v-if="permissionsLoading" class="permissions-skeleton" aria-busy="true" aria-label="Carregando permissões">
        <div v-for="n in 4" :key="n" class="permission-skeleton-row">
          <div class="skeleton-block skeleton-label"></div>
          <div class="skeleton-block skeleton-btn"></div>
        </div>
      </div>
      <p v-else-if="permissionsError" class="form-error" role="alert">{{ permissionsError.message }}</p>
      <div v-else class="permissions-list">
        <div v-for="permission in permissionCatalog" :key="permission.name" class="detail-row permission-row">
          <span><strong>{{ permissionLabel(permission.name) }}</strong><small v-if="hasRolePermission(permission.name)">Concedida pelo papel</small><small v-else-if="hasDirectPermission(permission.name)">Concessão individual</small></span>
          <button v-if="hasDirectPermission(permission.name)" class="outline-button danger-action" :disabled="permissionSaving" @click="revokePermission(permission.name)">Revogar</button>
          <button v-else-if="!hasRolePermission(permission.name)" class="outline-button" :disabled="permissionSaving" @click="grantPermission(permission.name)">Conceder</button>
          <span v-else class="gray">Herdada</span>
        </div>
      </div>
      <p v-if="permissionMessage" class="success-message" role="status">{{ permissionMessage }}</p>
      <div class="modal-actions"><button type="button" class="outline-button" @click="permissionUser = null">Fechar</button></div>
    </section>
  </div>
</template>

<style scoped>
/* ── Skeleton loader ── */
@keyframes shimmer {
  from { background-position: -200% 0; }
  to   { background-position:  200% 0; }
}

.skeleton-block {
  border-radius: 6px;
  background: linear-gradient(90deg, #e8f2f4 25%, #d0e8ec 50%, #e8f2f4 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
}

.permissions-skeleton {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 4px 0;
}

.permission-skeleton-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 4px;
  border-bottom: 1px solid var(--line);
}

.permission-skeleton-row:last-child {
  border-bottom: 0;
}

.skeleton-label {
  flex: 1;
  height: 12px;
}

.skeleton-btn {
  width: 64px;
  height: 30px;
  border-radius: 9px;
}

@media (prefers-color-scheme: dark) {
  .skeleton-block {
    background: linear-gradient(90deg, #112535 25%, #1a3545 50%, #112535 75%);
    background-size: 200% 100%;
  }
}
</style>
