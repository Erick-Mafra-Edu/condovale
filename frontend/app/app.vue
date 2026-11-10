<script setup lang="ts">
import type { CommonArea, Reservation } from '~/domain/reservation'
import { can, canViewModule, type UseCase } from '~/domain/permissions'
import { getPublishedNotices } from '~/domain/notice'
import type { UpdateOwnProfileInput } from '~/domain/user'
import type { CreateUnitOccupancyInput } from '~/domain/unit-occupancy'
import OccupancyPage from '~/components/OccupancyPage.vue'

const active = ref('Início')
const theme = ref<'light' | 'dark'>(import.meta.client && document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light')
const showOccurrence = ref(false)
const occurrenceTitle = ref('')
const occurrenceCategory = ref('')
const occurrenceDescription = ref('')
const occurrenceResidentId = ref('')
const occurrenceTypesError = ref('')
const occurrenceSuccess = ref('')
const submittingOccurrence = ref(false)
const updatingOccurrence = ref(false)
const occurrenceNote = ref('')
const occurrenceMessage = ref('')
const occurrenceTransferReason = ref('')
const occurrenceEmployeeId = ref('')
const occurrenceAttachmentError = ref('')
const occurrenceActionError = ref('')
const uploadingOccurrenceAttachment = ref(false)
const occurrenceAttachmentInput = ref<HTMLInputElement | null>(null)
const reviewingReservation = ref(false)
const reportView = ref<'occurrences' | 'reservations' | 'audit'>('occurrences')
const profileSaving = ref(false)
const profileError = ref('')
const profileSuccess = ref('')
const areas = ref<CommonArea[]>([])
const selectedAreaId = ref<string | null>(null)
const showNotifications = ref(false)
const detailTarget = ref<{ type: 'occurrence' | 'reservation' | 'notice'; id: string } | null>(null)
const isScrolled = ref(false)

function applyTheme(nextTheme: 'light' | 'dark') {
  theme.value = nextTheme
  if (import.meta.client) {
    document.documentElement.dataset.theme = nextTheme
    localStorage.setItem('condovale-theme', nextTheme)
    window.dispatchEvent(new Event('condovale-theme-change'))
  }
}

function setTheme(nextTheme: 'light' | 'dark') {
  applyTheme(nextTheme)
}

const { user: authUser, loading: authLoading, error: authError, authenticate, restoreSession, changePassword, logout } = useAuth()
const { public: runtimeConfig } = useRuntimeConfig()
const apiMode = computed(() => runtimeConfig.dataSource === 'api')
const { users, loading: usersLoading, loadUsers, updateOwnProfile, createUser, updateUser, deactivateUser } = useUsers()
const { units, loading: unitsLoading, loadUnits, createUnit, updateUnit, deactivateUnit } = useUnits()
const { occupancies, loading: occupanciesLoading, error: occupanciesError, loadOccupancies, findOccupancy, createOccupancy: createOccupancyRequest, removeOccupancy } = useUnitOccupancies()
const selectedOccupancy = ref<(typeof occupancies.value)[number] | null>(null)
const { occurrences, history: occurrenceHistory, assignableEmployees, attachments: occurrenceAttachments, loading: occurrencesLoading, error: occurrenceError, loadOccurrences, findOccurrence, createOccurrence, assignOccurrence, updateOccurrenceAttendance, loadAssignableEmployees, loadHistory, loadAttachments, uploadAttachment, attachmentUrl, updateOccurrenceStatus, finishOccurrence } = useOccurrences()
const { types: occurrenceTypes, activeTypes: activeOccurrenceTypes, loading: occurrenceTypesLoading, load: loadOccurrenceTypes, loadActive: loadActiveOccurrenceTypes, find: findOccurrenceType, create: createOccurrenceType, update: updateOccurrenceType, remove: removeOccurrenceType } = useOccurrenceTypes()
const { reservations, loading: reservationsLoading, error: reservationsError, loadReservations, loadMyReservations, listAreas, updateReservationStatus } = useReservations()
const { notices, loading: noticesLoading, error: noticesError, loadNotices, createNotice } = useNotices()
const { auditLogs, loadAuditLogs } = useAuditLogs()

const navItems = [
  { label: 'Início', icon: 'home' }, { label: 'Meu cadastro', icon: 'profile' }, { label: 'Usuários', icon: 'users' }, { label: 'Tipos de ocorrência', icon: 'alert' }, { label: 'Ocorrências', icon: 'occurrences' },
  { label: 'Reservas', icon: 'calendar' }, { label: 'Comunicados', icon: 'message' }, { label: 'Relatórios', icon: 'documents' }, { label: 'Unidades', icon: 'building' }, { label: 'Moradores e unidades', icon: 'users' },
] as const
const unavailableInApi = new Set(['Reservas', 'Comunicados', 'Relatórios'])

function hasAccess(useCase: UseCase) {
  return authUser.value ? can(authUser.value.role, useCase, authUser.value.permissions) : false
}

const canViewOccurrences = computed(() => ['create-occurrence', 'open-occurrence-for-resident', 'track-own-occurrences', 'view-assigned-occurrences', 'analyze-occurrences', 'assign-occurrence', 'close-any-occurrence'].some(useCase => hasAccess(useCase as UseCase)))
const canTransferOccurrence = computed(() => canAssignOccurrence.value || hasAccess('hand-over-occurrence' as UseCase))
const visibleNavItems = computed(() => navItems.filter(item => authUser.value && canViewModule(authUser.value.role, item.label, authUser.value.permissions) && (!apiMode.value || (!unavailableInApi.has(item.label) && (item.label !== 'Ocorrências' || canViewOccurrences.value)))))
const resident = computed(() => authUser.value?.role === 'resident' ? authUser.value : null)
const canCreateOccurrence = computed(() => authUser.value ? can(authUser.value.role, 'create-occurrence', authUser.value.permissions) : false)
const canOpenForResident = computed(() => authUser.value ? can(authUser.value.role, 'open-occurrence-for-resident', authUser.value.permissions) : false)
const canAssignOccurrence = computed(() => authUser.value ? can(authUser.value.role, 'assign-occurrence', authUser.value.permissions) : false)
const canAnalyzeOccurrence = computed(() => authUser.value ? can(authUser.value.role, 'analyze-occurrences', authUser.value.permissions) : false)
const canCommentOccurrence = computed(() => hasAccess('analyze-occurrences') || hasAccess('update-occurrence-progress'))
const loadActiveOccurrenceTypesRequest = () => loadActiveOccurrenceTypes().catch((cause) => { occurrenceTypesError.value = cause instanceof Error ? cause.message : 'Não foi possível carregar os tipos de ocorrência.' })
async function openOccurrenceForm() { showOccurrence.value = true; occurrenceSuccess.value = ''; occurrenceActionError.value = ''; occurrenceError.value = null; if (!activeOccurrenceTypes.value.length) await loadActiveOccurrenceTypesRequest() }
const roleLabel = computed(() => ({ resident: 'Morador', employee: 'Funcionário', syndic: 'Síndico', admin: 'Administrador' }[authUser.value?.role ?? 'resident']))
const areaById = computed(() => new Map(areas.value.map(area => [area.id, area])))
const activeResidents = computed(() => users.value.filter(user => user.role === 'resident' && user.status === 'active'))
const activeEmployees = computed(() => apiMode.value ? assignableEmployees.value : users.value.filter(user => user.role === 'employee' && user.status === 'active'))
const visibleOccurrences = computed(() => {
  if (authUser.value?.role === 'employee') return occurrences.value.filter(item => item.assignedEmployeeId === authUser.value?.id)
  if (authUser.value?.role === 'resident') return occurrences.value.filter(item => item.residentId === authUser.value?.id)
  return occurrences.value
})
const openOccurrences = computed(() => visibleOccurrences.value.filter(item => !['completed', 'cancelled'].includes(item.status)))
const activeReservations = computed(() => reservations.value.filter(item => ['approved', 'pending'].includes(item.status)))
const residentName = computed(() => authUser.value?.name ?? 'Morador')
const residentInitials = computed(() => residentName.value.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase())

const stats = computed(() => {
  const items = [
  { label: 'Moradores', value: String(activeResidents.value.length), note: 'moradores ativos', icon: 'users' as const, tone: 'cyan' },
  { label: 'Ocorrências abertas', value: String(openOccurrences.value.length), note: 'em acompanhamento', icon: 'alert' as const, tone: 'red' },
  { label: 'Reservas ativas', value: String(activeReservations.value.length), note: 'pendentes ou aprovadas', icon: 'calendar' as const, tone: 'blue' },
  { label: 'Comunicados', value: String(notices.value.filter(item => item.status === 'published').length), note: 'publicados', icon: 'message' as const, tone: 'orange' },
  ]
  return apiMode.value ? items.filter(item => item.label === 'Moradores' && hasAccess('manage-residents')) : items
})

const formatDate = (value: string) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`))
const formatDateTime = (value: string) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value))
const occurrenceLabel = (status: string) => ({ open: 'Aberta', analysis: 'Em análise', assigned: 'Atribuída', in_progress: 'Em andamento', completed: 'Concluída', cancelled: 'Cancelada' }[status] ?? status)
const occurrenceTone = (status: string) => ({ open: 'red', analysis: 'blue', assigned: 'yellow', in_progress: 'blue', completed: 'green', cancelled: 'gray' }[status] ?? 'gray')
const occurrenceEmployeeName = (id?: string, nestedName?: string) => id ? nestedName ?? users.value.find(item => item.id === id)?.name ?? activeEmployees.value.find(item => item.id === id)?.name ?? `Funcionário #${id}` : ''
const reservationLabel = (status: Reservation['status']) => ({ approved: 'Aprovada', pending: 'Pendente', rejected: 'Recusada', cancelled: 'Cancelada' }[status])
const reservationTone = (status: Reservation['status']) => ({ approved: 'green', pending: 'yellow', rejected: 'red', cancelled: 'gray' }[status])

const sortedOccurrences = computed(() => [...visibleOccurrences.value].sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)))
const recentOccurrences = computed(() => sortedOccurrences.value.slice(0, 3))
const upcomingReservations = computed(() => [...activeReservations.value].sort((a, b) => `${a.date}${a.startTime}`.localeCompare(`${b.date}${b.startTime}`)))
const filteredReservations = computed(() => selectedAreaId.value ? upcomingReservations.value.filter(item => item.areaId === selectedAreaId.value) : upcomingReservations.value)
const selectedOccurrence = computed(() => detailTarget.value?.type === 'occurrence' ? occurrences.value.find(item => item.id === detailTarget.value?.id) ?? null : null)
const canUploadSelectedOccurrence = computed(() => {
  const occurrence = selectedOccurrence.value
  return Boolean(occurrence && !['completed', 'cancelled'].includes(occurrence.status) && visibleOccurrences.value.some(item => item.id === occurrence.id))
})
const selectedReservation = computed(() => detailTarget.value?.type === 'reservation' ? reservations.value.find(item => item.id === detailTarget.value?.id) ?? null : null)
const selectedNotice = computed(() => detailTarget.value?.type === 'notice' ? notices.value.find(item => item.id === detailTarget.value?.id) ?? null : null)
const publishedNotices = computed(() => getPublishedNotices(notices.value))
const recentNotices = computed(() => publishedNotices.value.slice(0, 3))
const getArea = (areaId: string) => areaById.value.get(areaId)
const reservationMeta = (reservation: Reservation) => `${formatDate(reservation.date)} · ${reservation.startTime && reservation.endTime ? `${reservation.startTime}–${reservation.endTime}` : 'Dia inteiro'}`
const noticeMeta = (publishedAt: string, status: string) => status === 'published' ? `Publicado em ${formatDateTime(publishedAt)}` : 'Rascunho'
const noticeLabel = (status: string) => status === 'published' ? 'Publicado' : 'Rascunho'
const noticeTone = (status: string) => status === 'published' ? 'green' : 'gray'
const selectArea = (areaId: string | null) => { selectedAreaId.value = areaId }
const openDetail = (type: 'occurrence' | 'reservation' | 'notice', id: string) => { detailTarget.value = { type, id } }

async function openOccurrenceDetail(id: string) {
  occurrenceNote.value = ''
  occurrenceMessage.value = ''
  occurrenceTransferReason.value = ''
  occurrenceAttachmentError.value = ''
  occurrenceActionError.value = ''
  occurrenceEmployeeId.value = ''
  detailTarget.value = { type: 'occurrence', id }
  if (apiMode.value) {
    const detail = await findOccurrence(id).catch(() => undefined)
    if (detail) {
      const index = occurrences.value.findIndex(item => item.id === detail.id)
      if (index >= 0) occurrences.value[index] = detail
      else occurrences.value.push(detail)
    }
  }
  await loadHistory(id).catch(() => undefined)
  await loadAttachments(id).catch(() => undefined)
}

async function loadDashboard() {
  const canManageOccupancies = hasAccess('link-residents-to-units')
  const canManageUnits = hasAccess('manage-units')
  if (apiMode.value) {
    await Promise.all([
      hasAccess('manage-residents') || canAssignOccurrence.value ? loadUsers() : Promise.resolve(),
      canManageUnits || canManageOccupancies ? loadUnits() : Promise.resolve(),
      canManageOccupancies ? loadOccupancies() : Promise.resolve(),
      canViewOccurrences.value ? loadOccurrences() : Promise.resolve(),
      canTransferOccurrence.value ? loadAssignableEmployees() : Promise.resolve(),
      canCreateOccurrence.value || canOpenForResident.value ? loadActiveOccurrenceTypesRequest() : Promise.resolve(),
      hasAccess('manage-occurrence-types') ? loadOccurrenceTypes() : Promise.resolve(),
    ])
    return
  }

  const loadRelevantReservations = resident.value ? loadMyReservations() : loadReservations()
  const [areasResponse] = await Promise.all([listAreas(), loadUsers(), canManageUnits || canManageOccupancies ? loadUnits() : Promise.resolve(), loadOccurrences(), loadRelevantReservations, loadNotices(), hasAccess('view-audit-reports') ? loadAuditLogs() : Promise.resolve(), canManageOccupancies ? loadOccupancies() : Promise.resolve(), hasAccess('manage-occurrence-types') ? loadOccurrenceTypes() : Promise.resolve()])
  areas.value = areasResponse.data
}

const reloadReservations = () => resident.value ? loadMyReservations() : loadReservations()

async function handleLogin(email: string, password: string) {
  await authenticate({ email, password })
  reportView.value = 'occurrences'
  if (!authUser.value?.mustChangePassword) await loadDashboard()
}

async function handlePasswordChange(input: Parameters<typeof changePassword>[0]) {
  await changePassword(input)
  await loadDashboard()
}

async function handleLogout() {
  await logout()
  active.value = 'Início'
}

async function saveOwnProfile(input: UpdateOwnProfileInput) {
  if (!authUser.value || !hasAccess('update-own-profile')) return
  profileSaving.value = true
  profileError.value = ''
  profileSuccess.value = ''
  try {
    authUser.value = await updateOwnProfile(input)
    profileSuccess.value = 'Cadastro atualizado com sucesso.'
  } catch (cause) {
    profileError.value = cause instanceof Error ? cause.message : 'Não foi possível atualizar o cadastro.'
  } finally { profileSaving.value = false }
}

async function createManagedUser(input: Parameters<typeof createUser>[0]) {
  if (hasAccess('manage-residents')) return createUser(input)
  throw new Error('Sem permissão para gerenciar usuários.')
}

async function updateManagedUser(id: string, input: Parameters<typeof updateUser>[1]) {
  if (hasAccess('manage-residents')) await updateUser(id, input)
}

async function deactivateManagedUser(id: string) {
  if (hasAccess('manage-residents')) await deactivateUser(id)
}

async function createManagedUnit(input: Parameters<typeof createUnit>[0]) {
  if (hasAccess('manage-units')) await createUnit(input)
}

async function updateManagedUnit(id: string, input: Parameters<typeof updateUnit>[1]) {
  if (hasAccess('manage-units')) await updateUnit(id, input)
}

async function deactivateManagedUnit(id: string) {
  if (hasAccess('manage-units')) await deactivateUnit(id)
}

async function createManagedOccurrenceType(input: Parameters<typeof createOccurrenceType>[0]) {
  if (hasAccess('manage-occurrence-types')) return createOccurrenceType(input)
  throw new Error('Sem permissão para gerenciar tipos de ocorrência.')
}
async function updateManagedOccurrenceType(id: string, input: Parameters<typeof updateOccurrenceType>[1]) {
  if (hasAccess('manage-occurrence-types')) await updateOccurrenceType(id, input)
}
async function removeManagedOccurrenceType(id: string) {
  if (hasAccess('manage-occurrence-types')) await removeOccurrenceType(id)
}

async function submitOccurrence() {
  const residentId = canOpenForResident.value && occurrenceResidentId.value ? occurrenceResidentId.value : resident.value?.id
  if (!residentId || !occurrenceTitle.value.trim() || !occurrenceDescription.value.trim() || !occurrenceCategory.value) return
  submittingOccurrence.value = true
  occurrenceSuccess.value = ''
  occurrenceActionError.value = ''
  try {
    await createOccurrence({ title: occurrenceTitle.value.trim(), description: occurrenceDescription.value.trim(), occurrenceTypeId: occurrenceCategory.value, residentId })
    if (apiMode.value) await loadOccurrences()
    occurrenceTitle.value = ''
    occurrenceDescription.value = ''
    occurrenceResidentId.value = ''
    occurrenceSuccess.value = 'Ocorrência registrada com sucesso.'
  } catch (cause) {
    occurrenceActionError.value = cause instanceof Error ? cause.message : 'Não foi possível registrar a ocorrência.'
  } finally { submittingOccurrence.value = false }
}

async function reviewReservation(status: 'approved' | 'rejected') {
  if (!selectedReservation.value || !hasAccess('approve-or-reject-reservation')) return
  reviewingReservation.value = true
  try {
    await updateReservationStatus(selectedReservation.value.id, status)
    detailTarget.value = null
  } finally { reviewingReservation.value = false }
}

async function startOccurrence() {
  if (!selectedOccurrence.value || !authUser.value || !hasAccess('update-occurrence-progress')) return
  updatingOccurrence.value = true
  try {
    await updateOccurrenceStatus(selectedOccurrence.value.id, 'in_progress')
    await loadHistory(selectedOccurrence.value.id)
    occurrenceActionError.value = ''
  } catch (cause) {
    occurrenceActionError.value = cause instanceof Error ? cause.message : 'Não foi possível atualizar a ocorrência.'
  } finally { updatingOccurrence.value = false }
}

async function assignSelectedOccurrence() {
  if (!selectedOccurrence.value || !occurrenceEmployeeId.value || !canTransferOccurrence.value) return
  const reason = occurrenceTransferReason.value.trim()
  if (selectedOccurrence.value.status !== 'analysis' && !reason) return
  updatingOccurrence.value = true
  try {
    await assignOccurrence(selectedOccurrence.value.id, occurrenceEmployeeId.value, reason || undefined)
    occurrenceTransferReason.value = ''
    await loadHistory(selectedOccurrence.value.id)
    occurrenceActionError.value = ''
  } catch (cause) {
    occurrenceActionError.value = cause instanceof Error ? cause.message : 'Não foi possível encaminhar a ocorrência.'
  } finally { updatingOccurrence.value = false }
}

async function addOccurrenceMessage() {
  if (!selectedOccurrence.value || !canCommentOccurrence.value || !occurrenceMessage.value.trim()) return
  updatingOccurrence.value = true
  try {
    await updateOccurrenceAttendance(selectedOccurrence.value.id, { message: occurrenceMessage.value.trim() })
    occurrenceMessage.value = ''
    await loadHistory(selectedOccurrence.value.id)
    occurrenceActionError.value = ''
  } catch (cause) {
    occurrenceActionError.value = cause instanceof Error ? cause.message : 'Não foi possível adicionar a atualização.'
  } finally { updatingOccurrence.value = false }
}

async function uploadSelectedOccurrenceAttachment(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file || !selectedOccurrence.value) return
  occurrenceAttachmentError.value = ''
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    occurrenceAttachmentError.value = 'Envie uma imagem JPG, PNG ou WEBP.'
    if (occurrenceAttachmentInput.value) occurrenceAttachmentInput.value.value = ''
    return
  }
  uploadingOccurrenceAttachment.value = true
  try {
    await uploadAttachment(selectedOccurrence.value.id, file)
    await loadAttachments(selectedOccurrence.value.id)
  } catch (cause) {
    occurrenceAttachmentError.value = cause instanceof Error ? cause.message : 'Não foi possível enviar o anexo.'
  } finally {
    uploadingOccurrenceAttachment.value = false
    if (occurrenceAttachmentInput.value) occurrenceAttachmentInput.value.value = ''
  }
}

function openOccurrenceAttachment(id: string, url?: string) {
  if (!selectedOccurrence.value) return
  const target = url || attachmentUrl(selectedOccurrence.value.id, id)
  window.open(target, '_blank', 'noopener,noreferrer')
}

async function analyzeSelectedOccurrence() {
  if (!selectedOccurrence.value || !canAnalyzeOccurrence.value || selectedOccurrence.value.status !== 'open') return
  updatingOccurrence.value = true
  try {
    await updateOccurrenceStatus(selectedOccurrence.value.id, 'analysis')
    await loadHistory(selectedOccurrence.value.id).catch(() => undefined)
    occurrenceActionError.value = ''
  } catch (cause) {
    occurrenceActionError.value = cause instanceof Error ? cause.message : 'Não foi possível iniciar a análise.'
  } finally { updatingOccurrence.value = false }
}

async function completeOccurrence() {
  if (!selectedOccurrence.value || !authUser.value || !hasAccess('finish-occurrence')) return
  updatingOccurrence.value = true
  try {
    await finishOccurrence(selectedOccurrence.value.id, { resolved: true, conclusion: occurrenceNote.value.trim() })
    occurrenceNote.value = ''
    await loadHistory(selectedOccurrence.value.id)
    occurrenceActionError.value = ''
  } catch (cause) {
    occurrenceActionError.value = cause instanceof Error ? cause.message : 'Não foi possível finalizar o atendimento.'
  } finally { updatingOccurrence.value = false }
}

function updateScrollMaterial() {
  isScrolled.value = window.scrollY > 24
}

onMounted(() => {
  applyTheme(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light')
  restoreSession().then(user => {
    if (user && !user.mustChangePassword) return loadDashboard()
  }).catch(() => undefined)
  updateScrollMaterial()
  window.addEventListener('scroll', updateScrollMaterial, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', updateScrollMaterial))
const selectNav = (label: string) => {
  active.value = label
  if (label === 'Relatórios' && hasAccess('view-audit-reports')) loadAuditLogs().catch(() => undefined)
}

async function createOccupancy(input: CreateUnitOccupancyInput) {
  if (!hasAccess('link-residents-to-units')) return
  await createOccupancyRequest(input)
}

async function finishOccupancy(id: string) {
  if (!hasAccess('link-residents-to-units')) return
  await removeOccupancy(id)
}

async function openOccupancyDetail(id: string) {
  try { selectedOccupancy.value = await findOccupancy(id) } catch { selectedOccupancy.value = null }
}

function closeOccupancyDetail() { selectedOccupancy.value = null }
</script>

<template>
  <LoginScreen v-if="!authUser" :loading="authLoading" :error="authError" :show-demo-access="runtimeConfig.dataSource !== 'api'" @submit="handleLogin" />
  <PasswordChangePage v-else-if="authUser.mustChangePassword" :loading="authLoading" :error="authError" :on-submit="handlePasswordChange" :on-logout="handleLogout" />
  <div v-else class="app-shell">
    <AppSidebar :active="active" :items="visibleNavItems" @select="selectNav" />

    <main class="main-content">
      <AppTopbar :name="residentName" :initials="residentInitials" :notification-count="publishedNotices.length" :compact="isScrolled" :can-open-profile="hasAccess('update-own-profile')" :theme="theme" @theme-change="setTheme" @notifications="showNotifications = !showNotifications" @profile="selectNav('Meu cadastro')" @logout="handleLogout" />
      <aside v-if="showNotifications" class="notifications-popover glass"><div class="popover-head"><strong>Comunicados</strong><button aria-label="Fechar notificações" @click="showNotifications = false">×</button></div><div class="notifications-list"><div v-if="!publishedNotices.length" class="empty-row">Nenhum comunicado novo.</div><button v-for="notice in publishedNotices" :key="notice.id" class="notification-item" @click="active = 'Comunicados'; showNotifications = false"><SvgIcon name="message" /><span><strong>{{ notice.title }}</strong><small>{{ noticeMeta(notice.publishedAt, notice.status) }}</small></span></button></div></aside>

      <div class="page-wrap">
        <section class="page-heading">
          <div><div class="eyebrow">{{ roleLabel }} · Residencial Vale Verde</div><h1>Olá, {{ residentName }}!</h1><p>Veja o que está acontecendo no seu condomínio hoje.</p></div>
          <button v-if="!apiMode && hasAccess('view-notices')" class="primary-button" @click="active = 'Comunicados'"><SvgIcon name="message" />Ver comunicados</button>
        </section>

        <div v-if="active === 'Início'">
          <section class="stat-grid"><DashboardStat v-for="stat in stats" :key="stat.label" v-bind="stat" /></section>
          <section class="content-grid three-columns">
            <article v-if="!apiMode" class="panel"><div class="panel-head"><h2>Ocorrências recentes</h2><button @click="active = 'Ocorrências'">Ver todas</button></div><div class="list"><div v-if="!recentOccurrences.length" class="empty-row">Nenhuma ocorrência registrada.</div><button v-for="item in recentOccurrences" :key="item.id" class="list-row interactive-row" @click="openOccurrenceDetail(item.id)"><span class="dot teal"></span><span><strong>{{ item.title }}</strong><small>{{ item.category }} · {{ formatDateTime(item.createdAt) }}</small></span><em :class="occurrenceTone(item.status)">{{ occurrenceLabel(item.status) }}</em></button></div></article>
            <article v-if="!apiMode && (hasAccess('view-common-areas') || hasAccess('manage-reservations'))" class="panel"><div class="panel-head"><h2>Próximas reservas</h2><button @click="active = 'Reservas'">Ver agenda</button></div><div class="list"><div v-if="!upcomingReservations.length" class="empty-row">Nenhuma reserva ativa.</div><button v-for="item in upcomingReservations.slice(0, 3)" :key="item.id" class="list-row interactive-row" @click="openDetail('reservation', item.id)"><span class="row-icon"><SvgIcon name="calendar" /></span><span><strong>{{ getArea(item.areaId)?.name ?? 'Área comum' }}</strong><small>{{ reservationMeta(item) }}</small></span><em :class="reservationTone(item.status)">{{ reservationLabel(item.status) }}</em></button></div></article>
            <article v-if="!apiMode && (hasAccess('view-notices') || hasAccess('publish-notices'))" class="panel"><div class="panel-head"><h2>Comunicados recentes</h2><button @click="active = 'Comunicados'">Ver todos</button></div><div class="list"><div v-if="!recentNotices.length" class="empty-row">Nenhum comunicado publicado.</div><button v-for="item in recentNotices" :key="item.id" class="list-row interactive-row" @click="openDetail('notice', item.id)"><span class="row-icon"><SvgIcon name="message" /></span><span><strong>{{ item.title }}</strong><small>{{ noticeMeta(item.publishedAt, item.status) }}</small></span><em :class="noticeTone(item.status)">{{ noticeLabel(item.status) }}</em></button></div></article>
          </section>
          <section class="welcome-banner"><div><h2>Condomínio melhor <span>quando as pessoas se conectam.</span></h2><p>Participe, registre, reserve e acompanhe. Tudo em um só lugar.</p></div><div class="people-illustration"><SvgIcon name="users" /><SvgIcon name="users" /><SvgIcon name="users" /></div><div class="banner-logo"><SvgIcon name="brand" /><small>CondoVale</small></div></section>
        </div>

        <ProfilePage v-else-if="active === 'Meu cadastro' && authUser && hasAccess('update-own-profile')" :user="authUser" :saving="profileSaving" :error="profileError" :success="profileSuccess" @save="saveOwnProfile" />
        <UsersAdminPage v-else-if="active === 'Usuários' && hasAccess('manage-residents')" :users="users" :loading="usersLoading" :current-user-id="authUser.id" :on-create="createManagedUser" :on-update="updateManagedUser" :on-deactivate="deactivateManagedUser" />
        <OccurrenceTypeAdminPage v-else-if="active === 'Tipos de ocorrência' && hasAccess('manage-occurrence-types')" :types="occurrenceTypes" :loading="occurrenceTypesLoading" :error="occurrenceTypesError" :on-find="findOccurrenceType" :on-create="createManagedOccurrenceType" :on-update="updateManagedOccurrenceType" :on-remove="removeManagedOccurrenceType" />
        <UnitAdminPage v-else-if="active === 'Unidades' && hasAccess('manage-units')" :units="units" :loading="unitsLoading" :on-create="createManagedUnit" :on-update="updateManagedUnit" :on-deactivate="deactivateManagedUnit" />
        <section v-else-if="active === 'Relatórios'" class="reports-shell"><nav v-if="hasAccess('generate-reports')" class="report-kind-tabs"><button :class="{ selected: reportView === 'occurrences' }" @click="reportView = 'occurrences'">Ocorrências</button><button :class="{ selected: reportView === 'reservations' }" @click="reportView = 'reservations'">Reservas</button><button v-if="hasAccess('view-audit-reports')" :class="{ selected: reportView === 'audit' }" @click="reportView = 'audit'">Auditoria</button></nav><AuditReportPage v-if="reportView === 'audit' && hasAccess('view-audit-reports')" :logs="auditLogs" :users="users" /><ReservationReportPage v-else-if="reportView === 'reservations' && hasAccess('generate-reports')" :reservations="reservations" :areas="areas" :loading="reservationsLoading" :error="reservationsError" /><ReportPage v-else-if="hasAccess('generate-reports')" :occurrences="occurrences" /></section>
        <ReservationPage v-else-if="active === 'Reservas'" :areas="areas" :reservations="reservations" :resident-id="resident?.id" :can-manage="hasAccess('approve-or-reject-reservation')" @reservation-created="reloadReservations" @reservation-cancelled="reloadReservations" @open-details="openDetail('reservation', $event)" />
        <OccupancyPage v-else-if="active === 'Moradores e unidades' && hasAccess('link-residents-to-units')" :occupancies="occupancies" :selected-occupancy="selectedOccupancy" :users="users" :units="units" :loading="occupanciesLoading" :error="occupanciesError" @refresh="loadOccupancies" @create="createOccupancy" @delete="finishOccupancy" @open-detail="openOccupancyDetail" @close-detail="closeOccupancyDetail" />
        <div v-else>
          <section class="section-intro"><div class="section-icon"><SvgIcon :name="active === 'Reservas' ? 'calendar' : active === 'Ocorrências' ? 'alert' : 'message'" /></div><div><h2>{{ active }}</h2><p>{{ active === 'Reservas' ? 'Agende e acompanhe os espaços do condomínio.' : active === 'Ocorrências' ? 'Registre solicitações e acompanhe cada atendimento.' : 'Informação importante para viver melhor em comunidade.' }}</p></div><button v-if="active === 'Ocorrências' && (canCreateOccurrence || canOpenForResident)" class="primary-button" @click="openOccurrenceForm">Nova ocorrência</button></section>
          <NoticePage v-if="active === 'Comunicados'" :notices="notices" :loading="noticesLoading" :error="noticesError?.message" :can-publish="hasAccess('publish-notices')" :author-id="authUser.id" :on-create="createNotice" />
          <article class="panel detail-panel" v-else><div v-if="!sortedOccurrences.length" class="empty-row">Nenhuma ocorrência registrada.</div><button v-for="item in sortedOccurrences" :key="item.id" class="detail-row interactive-row" @click="openOccurrenceDetail(item.id)"><span class="row-icon large"><SvgIcon name="alert" /></span><span><strong>{{ item.title }}</strong><small>{{ item.category }} · {{ formatDateTime(item.createdAt) }}</small></span><em :class="occurrenceTone(item.status)">{{ occurrenceLabel(item.status) }}</em><span class="arrow"></span></button></article>
        </div>
      </div>
    </main>

    <MobileNav :active="active" :items="visibleNavItems" @select="selectNav" />
    <div v-if="showOccurrence" class="modal-backdrop" @click.self="showOccurrence = false"><div class="modal glass"><button class="modal-close" @click="showOccurrence = false" aria-label="Fechar"></button><div class="section-icon"><SvgIcon name="alert" /></div><h2>Abrir ocorrência</h2><p>Conte para a gente o que está acontecendo.</p><p v-if="occurrenceTypesError || occurrenceError" class="form-error" role="alert">{{ occurrenceTypesError || occurrenceError?.message }}</p><p v-if="occurrenceSuccess" class="form-success" role="status">{{ occurrenceSuccess }}</p><label v-if="canOpenForResident && activeResidents.length">Morador responsável<select v-model="occurrenceResidentId" required><option value="">Selecione o morador</option><option v-for="item in activeResidents" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label v-else-if="canOpenForResident">ID do morador responsável<input v-model="occurrenceResidentId" inputmode="numeric" placeholder="Informe o resident_id" required /><small class="field-help">A API não permite listar moradores para este perfil; informe o identificador cadastrado.</small></label><label>Título<input v-model="occurrenceTitle" placeholder="Ex.: Vazamento na garagem" required /></label><label>Tipo de ocorrência<select v-model="occurrenceCategory" required :disabled="occurrenceTypesLoading"><option value="">Selecione o tipo</option><option v-for="item in activeOccurrenceTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select></label><label>Descrição<textarea v-model="occurrenceDescription" placeholder="Descreva a ocorrência..." required></textarea></label><div class="modal-actions"><button class="outline-button" @click="showOccurrence = false">Cancelar</button><button class="primary-button" :disabled="submittingOccurrence || occurrenceTypesLoading || !occurrenceCategory || (!resident && !occurrenceResidentId)" @click="submitOccurrence">{{ submittingOccurrence ? 'Enviando...' : 'Registrar ocorrência' }}</button></div></div></div>
          <DetailModal v-if="selectedOccurrence" title="Detalhes da ocorrência" icon="alert" @close="detailTarget = null">
            <p v-if="occurrenceActionError" class="form-error" role="alert">{{ occurrenceActionError }}</p>
            <dl class="detail-list"><div><dt>Status</dt><dd><em :class="occurrenceTone(selectedOccurrence.status)">{{ occurrenceLabel(selectedOccurrence.status) }}</em></dd></div><div><dt>Categoria</dt><dd>{{ selectedOccurrence.occurrenceTypeName ?? selectedOccurrence.category }}</dd></div><div><dt>Registrada em</dt><dd>{{ formatDateTime(selectedOccurrence.createdAt) }}</dd></div><div v-if="selectedOccurrence.assignedEmployeeId" class="full"><dt>Responsável</dt><dd>{{ occurrenceEmployeeName(selectedOccurrence.assignedEmployeeId, selectedOccurrence.assignedEmployeeName) }}</dd></div><div class="full"><dt>Descrição</dt><dd>{{ selectedOccurrence.description }}</dd></div><div v-if="occurrenceHistory.length" class="full"><dt>Histórico</dt><dd class="occurrence-history"><span v-for="entry in occurrenceHistory" :key="entry.id"><strong>{{ occurrenceLabel(entry.newStatus ?? selectedOccurrence.status) }}</strong><small>{{ entry.message || formatDateTime(entry.createdAt) }}</small></span></dd></div></dl>
            <label v-if="canCommentOccurrence && selectedOccurrence.status !== 'completed' && selectedOccurrence.status !== 'cancelled'" class="occurrence-note">Atualização do atendimento<textarea v-model="occurrenceMessage" placeholder="Descreva uma atualização para o histórico"></textarea></label>
            <div v-if="canCommentOccurrence && selectedOccurrence.status !== 'completed' && selectedOccurrence.status !== 'cancelled'" class="modal-actions occurrence-actions"><button class="primary-button" :disabled="updatingOccurrence || !occurrenceMessage.trim()" @click="addOccurrenceMessage">{{ updatingOccurrence ? 'Salvando...' : 'Adicionar atualização' }}</button></div>
            <section class="occurrence-attachments full"><div class="occurrence-section-head"><strong>Anexos</strong><label v-if="canUploadSelectedOccurrence" class="outline-button occurrence-upload">{{ uploadingOccurrenceAttachment ? 'Enviando...' : 'Adicionar imagem' }}<input ref="occurrenceAttachmentInput" type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploadingOccurrenceAttachment" @change="uploadSelectedOccurrenceAttachment"></label></div><p v-if="occurrenceAttachmentError" class="form-error">{{ occurrenceAttachmentError }}</p><div v-if="occurrenceAttachments.length" class="occurrence-attachment-grid"><button v-for="attachment in occurrenceAttachments" :key="attachment.id" class="occurrence-attachment" type="button" @click="openOccurrenceAttachment(attachment.id, attachment.url)"><img :src="attachment.url || attachmentUrl(selectedOccurrence.id, attachment.id)" :alt="attachment.originalName"><small>{{ attachment.originalName }}</small></button></div><p v-else class="empty-row">Nenhum anexo.</p></section>
            <div v-if="canAnalyzeOccurrence && selectedOccurrence.status === 'open'" class="modal-actions occurrence-actions"><button class="primary-button" :disabled="updatingOccurrence" @click="analyzeSelectedOccurrence">{{ updatingOccurrence ? 'Salvando...' : 'Iniciar análise' }}</button></div>
            <div v-if="canTransferOccurrence && ['analysis', 'assigned', 'in_progress'].includes(selectedOccurrence.status)" class="occurrence-transfer"><select v-model="occurrenceEmployeeId" aria-label="Responsável pela ocorrência"><option value="">Selecione o funcionário</option><option v-for="employee in activeEmployees" :key="employee.id" :value="employee.id">{{ employee.name }}</option></select><label v-if="selectedOccurrence.status !== 'analysis'" class="occurrence-note">Justificativa do repasse<textarea v-model="occurrenceTransferReason" maxlength="255" placeholder="Informe por que a ocorrência está sendo repassada"></textarea></label><button class="primary-button" :disabled="updatingOccurrence || !occurrenceEmployeeId || (selectedOccurrence.status !== 'analysis' && !occurrenceTransferReason.trim())" @click="assignSelectedOccurrence">{{ updatingOccurrence ? 'Encaminhando...' : selectedOccurrence.status === 'analysis' ? 'Encaminhar' : 'Repassar' }}</button></div>
            <label v-if="selectedOccurrence.status === 'in_progress' && hasAccess('finish-occurrence')" class="occurrence-note">Observação da conclusão<textarea v-model="occurrenceNote" placeholder="Descreva a conclusão do atendimento"></textarea></label><div v-if="selectedOccurrence.status === 'assigned' && hasAccess('update-occurrence-progress')" class="modal-actions occurrence-actions"><button class="primary-button" :disabled="updatingOccurrence" @click="startOccurrence">{{ updatingOccurrence ? 'Salvando...' : 'Atualizar para em andamento' }}</button></div><div v-else-if="selectedOccurrence.status === 'in_progress' && hasAccess('finish-occurrence')" class="modal-actions occurrence-actions"><button class="primary-button" :disabled="updatingOccurrence || !occurrenceNote.trim()" @click="completeOccurrence">{{ updatingOccurrence ? 'Finalizando...' : 'Finalizar atendimento' }}</button></div>
          </DetailModal>
    <DetailModal v-if="selectedReservation" title="Detalhes da reserva" icon="calendar" @close="detailTarget = null"><dl class="detail-list"><div><dt>Área comum</dt><dd>{{ getArea(selectedReservation.areaId)?.name ?? 'Área comum' }}</dd></div><div><dt>Status</dt><dd><em :class="reservationTone(selectedReservation.status)">{{ reservationLabel(selectedReservation.status) }}</em></dd></div><div><dt>Horário</dt><dd>{{ reservationMeta(selectedReservation) }}</dd></div><div><dt>Capacidade</dt><dd>{{ getArea(selectedReservation.areaId)?.capacity ?? 'Não informada' }} pessoas</dd></div><div class="full"><dt>Solicitada em</dt><dd>{{ formatDateTime(selectedReservation.createdAt) }}</dd></div></dl><div v-if="hasAccess('approve-or-reject-reservation') && selectedReservation.status === 'pending'" class="modal-actions reservation-review-actions"><button class="outline-button danger-action" :disabled="reviewingReservation" @click="reviewReservation('rejected')"><SvgIcon name="reject" />Reprovar</button><button class="primary-button approve-action" :disabled="reviewingReservation" @click="reviewReservation('approved')"><SvgIcon name="approve" />{{ reviewingReservation ? 'Salvando...' : 'Aprovar reserva' }}</button></div></DetailModal>
    <DetailModal v-if="selectedNotice" title="Detalhes do comunicado" icon="message" @close="detailTarget = null"><dl class="detail-list"><div><dt>Status</dt><dd><em :class="noticeTone(selectedNotice.status)">{{ noticeLabel(selectedNotice.status) }}</em></dd></div><div><dt>Publicado em</dt><dd>{{ formatDateTime(selectedNotice.publishedAt) }}</dd></div><div class="full"><dt>Mensagem</dt><dd>{{ selectedNotice.content }}</dd></div></dl></DetailModal>
  </div>
</template>

<style>

*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--navy);font-family:Inter,Arial,sans-serif}button{font:inherit;cursor:pointer;border:0;background:none;color:inherit}.app-shell{min-height:100vh;display:flex}.sidebar{width:242px;background:#092f38;color:#d5f5f5;display:flex;flex-direction:column;padding:28px 16px 22px;position:fixed;inset:0 auto 0 0}.brand{font:800 20px 'Plus Jakarta Sans',sans-serif;display:flex;gap:8px;align-items:center;color:#fff}.brand>span:last-child>span,.mobile-brand>span:last-child{color:#24c5cf}.brand-mark{font-size:29px;color:#15bdc9;line-height:1}.brand-tag{font-size:7px;letter-spacing:1.7px;margin:3px 0 36px 40px;color:#9bd9dd}.nav-item{width:100%;display:flex;align-items:center;gap:14px;color:#a7c4c7;font-size:12px;padding:12px 14px;border-radius:9px;text-align:left;margin:3px 0}.nav-item.selected{background:var(--primary);color:#fff;box-shadow:0 8px 20px #001c2140}.nav-icon{width:17px;text-align:center;font-size:16px}.sidebar-spacer{flex:1}.unit-card{display:flex;align-items:center;gap:9px;background:#12424a;padding:11px;border-radius:11px;font-size:10px}.unit-card strong,.unit-card small{display:block}.unit-card small{font-size:8px;color:#9bc5c8;margin-top:3px}.unit-card span{margin-left:auto;font-size:22px}.unit-photo{background:#56a7a6;color:white;border-radius:6px;padding:8px 5px}.sidebar-foot{font-size:10px;color:#91b9bd;display:flex;justify-content:space-between;margin-top:20px;padding:0 8px}.main-content{margin-left:242px;width:calc(100% - 242px);min-height:100vh;background:transparent}.topbar{height:76px;margin:15px 26px 0;padding:13px 20px;border-radius:22px;display:flex;align-items:center;gap:24px}.glass{background:rgba(255,255,255,.78);border:1px solid rgba(255,255,255,.9);box-shadow:0 8px 30px rgba(16,42,67,.07)}.search{height:42px;display:flex;align-items:center;gap:12px;background:#f4fafb;border:1px solid var(--line);border-radius:12px;padding:0 16px;color:var(--neutral);font-size:13px;flex:1;max-width:430px}.top-actions{display:flex;align-items:center;gap:12px;margin-left:auto}.icon-button{position:relative;font-size:20px;color:var(--neutral)}.icon-button b{position:absolute;background:#ed5d66;color:#fff;border-radius:10px;font-size:9px;padding:2px 5px;top:-5px;right:-7px}.profile-trigger{display:flex;align-items:center;gap:12px;padding:3px;border-radius:12px;text-align:left}.profile-trigger:hover{background:rgba(0,140,158,.08)}.profile-trigger:focus-visible{outline:3px solid rgba(0,140,158,.35);outline-offset:2px}.avatar{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--soft);color:var(--deep);font-size:11px;font-weight:700}.greeting small,.greeting strong{display:block;font-size:10px}.greeting strong{font-size:12px;margin-top:3px}.page-wrap{padding:28px 30px 50px;max-width:1500px;margin:auto}.page-heading{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:24px}.eyebrow{font-size:10px;color:var(--neutral);margin-bottom:8px}h1,h2,p{margin:0}h1{font:800 28px 'Plus Jakarta Sans',sans-serif;letter-spacing:-.7px}h1 span{font-size:23px}.page-heading p{font-size:12px;color:var(--neutral);margin-top:5px}.primary-button{background:var(--primary);color:#fff;border-radius:10px;padding:11px 16px;font-size:12px;font-weight:700;box-shadow:0 7px 15px #008c9e2b}.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}.stat-card,.panel{background:#fff;border:1px solid #e6eff1;border-radius:15px;box-shadow:0 6px 18px rgba(16,42,67,.055)}.stat-card{padding:17px 18px}.stat-head{display:flex;align-items:center;justify-content:space-between;color:var(--neutral);font-size:11px;margin-bottom:17px}.stat-head i{font-style:normal;display:grid;place-items:center;width:29px;height:29px;border-radius:9px;background:var(--soft);color:var(--primary);font-weight:700}.stat-head i.red,.stat-card small.red{color:#e6575f}.stat-head i.red{background:#ffedef}.stat-head i.orange,.stat-card small.orange{color:#e99028}.stat-head i.orange{background:#fff5e6}.stat-head i.blue,.stat-card small.blue{color:#2c82d7}.stat-head i.blue{background:#eaf4ff}.stat-card>strong{font:700 25px 'Plus Jakarta Sans',sans-serif;display:block}.stat-card small{display:block;color:var(--primary);font-size:10px;margin-top:8px}.content-grid{display:grid;gap:16px}.three-columns{grid-template-columns:repeat(3,1fr)}.panel{padding:17px 18px}.panel-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.panel-head h2{font:700 14px 'Plus Jakarta Sans',sans-serif}.panel-head button{font-size:10px;color:var(--primary);font-weight:600}.list-row{display:flex;align-items:center;gap:10px;border-top:1px solid #edf2f3;padding:13px 0;min-height:56px}.list-row:first-child{border-top:0;padding-top:4px}.list-row>div,.detail-row>div:nth-child(2){flex:1}.list-row strong,.list-row small,.detail-row strong,.detail-row small{display:block}.list-row strong,.detail-row strong{font-size:11px;font-weight:600}.list-row small,.detail-row small{font-size:9px;color:var(--neutral);margin-top:4px}.dot{width:7px;height:7px;border-radius:50%;background:var(--primary)}.row-icon{display:grid;place-items:center;width:28px;height:28px;border-radius:50%;background:var(--soft);color:var(--primary);font-size:14px}.row-icon.large{width:38px;height:38px}.arrow{font-size:20px;color:#a7bbc1}.list-row em,.detail-row em{font-size:8px;font-style:normal;border-radius:12px;padding:4px 7px;white-space:nowrap}.red{color:#ef626c;background:#ffedef}.yellow{color:#b77919;background:#fff6dd}.blue{color:#2d82cd;background:#e9f4ff}.green{color:#159d68;background:#e5faef}.gray{color:#718096;background:#f1f4f5}.welcome-banner{margin-top:20px;padding:20px 26px;border-radius:17px;background:transparent;display:flex;align-items:center;gap:24px;overflow:hidden}.welcome-banner h2{font:700 18px 'Plus Jakarta Sans',sans-serif}.welcome-banner h2 span{color:var(--primary)}.welcome-banner p{font-size:11px;color:var(--neutral);margin-top:6px}.people-illustration{font-size:42px;color:var(--primary);margin-left:auto}.banner-logo{text-align:center;color:var(--primary);font-size:35px;font-weight:700}.banner-logo small{font-size:11px;color:var(--navy)}.section-intro{display:flex;align-items:center;gap:16px;margin-bottom:20px}.section-intro h2{font:700 24px 'Plus Jakarta Sans',sans-serif}.section-intro p{font-size:12px;color:var(--neutral);margin-top:4px}.section-intro .primary-button{margin-left:auto}.section-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:var(--soft);color:var(--primary);font-weight:800;font-size:20px}.detail-panel{padding:9px 20px}.filter-row{display:flex;gap:8px;padding:8px 0 16px;border-bottom:1px solid var(--line)}.filter{padding:8px 12px;border-radius:9px;background:#f4f8f9;color:var(--neutral);font-size:10px}.filter.selected{background:var(--soft);color:var(--deep);font-weight:700}.detail-row{display:flex;align-items:center;gap:15px;padding:16px 4px;border-bottom:1px solid var(--line)}.detail-row:last-child{border:0}.place-thumb{width:50px;height:38px;border-radius:8px;display:grid;place-items:center;background:#d7eff0;color:var(--primary);font-size:20px}.available{font-size:10px;color:#159d68;background:#e5faef;border-radius:12px;padding:5px 8px}.outline-button{border:1px solid #9cdae0;color:var(--deep);border-radius:9px;padding:9px 13px;font-size:10px;font-weight:700}.mobile-brand,.mobile-nav{display:none}.modal-backdrop{position:fixed;inset:0;background:#102a4366;display:grid;place-items:center;z-index:5;padding:20px}.modal{width:min(440px,100%);padding:28px;border-radius:24px;position:relative}.modal h2{font:700 22px 'Plus Jakarta Sans',sans-serif;margin-top:16px}.modal p{font-size:12px;color:var(--neutral);margin:5px 0 22px}.modal-close{position:absolute;right:18px;top:14px;font-size:24px;color:var(--neutral)}label{font-size:11px;font-weight:600;display:block;margin-top:14px}select,textarea{font:inherit;width:100%;margin-top:7px;border:1px solid var(--line);border-radius:9px;padding:11px;background:#fbfefe;color:var(--navy);font-size:11px}textarea{min-height:90px;resize:vertical}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
@media(max-width:900px){.sidebar{display:none}.main-content{margin-left:0;width:100%;padding-bottom:76px}.topbar{margin:10px 12px 0;height:62px;padding:10px 14px;border-radius:18px}.mobile-brand{display:block;font:800 15px 'Plus Jakarta Sans',sans-serif;white-space:nowrap}.mobile-brand .brand-mark{font-size:21px}.search{max-width:100%;height:38px;padding:0 11px}.greeting{display:none}.page-wrap{padding:22px 14px}.page-heading{display:block}.page-heading h1{font-size:24px}.page-heading .primary-button{width:100%;margin-top:16px}.eyebrow{font-size:9px}.stat-grid{grid-template-columns:repeat(2,1fr);gap:10px}.stat-card{padding:14px}.stat-head{font-size:9px;margin-bottom:13px}.stat-card>strong{font-size:21px}.three-columns{grid-template-columns:1fr}.content-grid{gap:12px}.welcome-banner{display:none}.section-intro{align-items:flex-start;flex-wrap:wrap}.section-intro .primary-button{width:100%;margin-left:0}.section-intro h2{font-size:20px}.detail-panel{padding:8px 12px}.filter-row{overflow:auto;white-space:nowrap}.detail-row{gap:10px}.detail-row .outline-button{padding:8px}.mobile-nav{position:fixed;display:flex;z-index:4;bottom:10px;left:10px;right:10px;justify-content:space-around;padding:8px 4px;border-radius:19px}.mobile-nav button{display:flex;flex-direction:column;align-items:center;gap:3px;font-size:8px;color:var(--neutral);padding:4px 6px}.mobile-nav button span{font-size:17px}.mobile-nav button.selected{color:var(--primary);font-weight:700}.top-actions{gap:8px}.avatar{width:32px;height:32px}.icon-button{font-size:17px}}
.condo-brand{font:800 20px 'Plus Jakarta Sans',sans-serif;display:flex;gap:8px;align-items:center;color:#fff;position:relative}.condo-brand .brand-name span{color:#24c5cf}.condo-brand small{position:absolute;top:28px;left:38px;font:7px Inter,sans-serif;letter-spacing:1.7px;color:#9bd9dd;white-space:nowrap}.brand-mark{display:grid;place-items:center}.brand-mark .svg-icon{width:27px;height:27px}.sidebar nav{margin-top:47px}.topbar .condo-brand{display:none}.svg-icon{display:block;width:1em;height:1em;object-fit:contain;filter:invert(42%) sepia(97%) hue-rotate(142deg) brightness(89%) }.nav-icon .svg-icon{width:17px;height:17px;filter:invert(86%) sepia(15%) hue-rotate(131deg) brightness(84%) }.nav-item.selected .svg-icon,.primary-button .svg-icon{filter:brightness(0) invert(1)}.primary-button{display:inline-flex;align-items:center;gap:7px}.primary-button:disabled{opacity:.55;cursor:not-allowed}.primary-button .svg-icon{width:14px;height:14px}.row-icon .svg-icon,.section-icon .svg-icon{width:16px;height:16px}.row-icon.large .svg-icon{width:20px;height:20px}.stat-head i .svg-icon{width:15px;height:15px}.stat-head i.red .svg-icon{filter:invert(42%) sepia(58%) hue-rotate(314deg) brightness(96%) }.stat-head i.orange .svg-icon{filter:invert(62%) sepia(56%) hue-rotate(353deg) brightness(98%) }.stat-head i.blue .svg-icon{filter:invert(50%) sepia(45%) hue-rotate(171deg) brightness(89%) }.icon-button .svg-icon{width:19px;height:19px}.unit-photo .svg-icon{width:16px;height:16px;filter:brightness(0) invert(1)}.people-illustration{display:flex;gap:12px}.people-illustration .svg-icon{width:36px;height:36px}.banner-logo .svg-icon{width:35px;height:35px;margin:auto}.place-thumb .svg-icon{width:21px;height:21px}.arrow,.action-arrow,.chevron{display:inline-block;width:7px;height:7px;border-top:1.5px solid currentColor;border-right:1.5px solid currentColor;transform:rotate(45deg);flex:none}.action-arrow{margin-left:7px;width:5px;height:5px}.more-icon{width:15px;height:10px;border-top:2px solid currentColor;border-bottom:2px solid currentColor;position:relative}.more-icon::after,.modal-close::before,.modal-close::after{content:'';position:absolute;background:currentColor}.modal-close{width:20px;height:20px}.modal-close::before,.modal-close::after{width:14px;height:1.5px;top:9px;left:3px;transform:rotate(45deg)}.modal-close::after{transform:rotate(-45deg)}.empty-row{color:var(--neutral);font-size:11px;padding:18px 0;text-align:center}.modal input,.modal select,.modal textarea{font:inherit;width:100%;margin-top:7px;border:1px solid var(--line);border-radius:9px;padding:11px;background:#fbfefe;color:var(--navy);font-size:11px}.notifications-popover{position:fixed;z-index:6;top:82px;right:30px;width:min(330px,calc(100vw - 28px));padding:12px;border-radius:18px}.popover-head{display:flex;align-items:center;justify-content:space-between;padding:4px 5px 10px;font-size:13px}.popover-head button{font-size:20px;color:var(--neutral)}.notification-item{width:100%;display:flex;gap:10px;text-align:left;padding:12px 6px;border-top:1px solid var(--line)}.notification-item .svg-icon{width:17px;height:17px;flex:none;margin-top:2px}.notification-item span{display:grid;gap:3px}.notification-item strong{font-size:11px}.notification-item small{font-size:9px;color:var(--neutral)}.interactive-row{width:100%;text-align:left;transition:background .18s ease,transform .18s ease}.interactive-row:hover{background:#f5fbfc}.interactive-row:active{transform:scale(.992)}.interactive-row>span:nth-child(2){flex:1;min-width:0}.interactive-row>span:nth-child(2) strong,.interactive-row>span:nth-child(2) small{display:block}.detail-modal{animation:modal-in .22s cubic-bezier(.2,.8,.2,1)}.detail-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:22px 0 0}.detail-list div{padding:12px;border-radius:11px;background:rgba(221,246,247,.52)}.detail-list .full{grid-column:1/-1}.detail-list dt{font-size:9px;color:var(--neutral);margin-bottom:5px}.detail-list dd{margin:0;font-size:12px;line-height:1.5}.detail-list em{font-size:9px;font-style:normal;border-radius:12px;padding:4px 7px}.reservation-details{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:12px 0;padding:12px;border-radius:10px;background:var(--soft)}.reservation-details strong,.reservation-details small{display:block}.reservation-details strong{font-size:11px}.reservation-details small{font-size:9px;color:var(--neutral);margin-top:3px}@keyframes modal-in{from{opacity:0;transform:translateY(10px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}@media(max-width:900px){.topbar .condo-brand{display:flex;font-size:15px;white-space:nowrap}.topbar .condo-brand .brand-mark{font-size:21px}.notifications-popover{top:72px;right:14px}.reservation-details{align-items:flex-start;flex-direction:column}.mobile-nav{bottom:12px;left:12px;right:12px;padding:7px 8px;border:1px solid rgba(255,255,255,.8);background:rgba(223,246,247,.72);box-shadow:0 12px 32px rgba(16,42,67,.16),inset 0 1px 0 rgba(255,255,255,.85);isolation:isolate}.mobile-nav button{position:relative;min-width:50px;border-radius:13px;transition:color .2s ease,transform .2s ease,background .25s ease}.mobile-nav button:active{transform:scale(.91)}.mobile-nav button.selected{background:rgba(255,255,255,.62);box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 3px 10px rgba(0,106,120,.12);animation:liquid-select .28s cubic-bezier(.2,.8,.2,1)}.mobile-nav button.selected::after{content:'';position:absolute;bottom:1px;width:4px;height:4px;border-radius:50%;background:var(--primary);box-shadow:0 0 0 3px rgba(0,140,158,.12)}.mobile-nav .svg-icon{width:17px;height:17px;transition:transform .25s ease,filter .2s ease}.mobile-nav button.selected .svg-icon{transform:translateY(-1px) scale(1.08)}.detail-list{grid-template-columns:1fr}}@keyframes liquid-select{0%{transform:scale(.88);filter:brightness(1.08)}70%{transform:scale(1.04)}100%{transform:scale(1)}}@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
/* Liquid Glass material: reservado para navegação, controles e superfícies elevadas. */
.main-content{background:transparent}
.glass{position:relative;isolation:isolate;overflow:hidden;background:transparent;border:1px solid transparent;background-clip:padding-box;box-shadow:0 12px 30px rgba(16,42,67,.10),0 2px 8px rgba(0,106,120,.06),inset 0 1px 0 rgba(255,255,255,.72);transition:transform .28s cubic-bezier(.2,.8,.2,1),box-shadow .28s cubic-bezier(.2,.8,.2,1),background .28s ease,border-radius .28s cubic-bezier(.2,.8,.2,1)}
.glass::before{content:'';position:absolute;inset:0;border-radius:inherit;pointer-events:none;z-index:-1;background:transparent;mask-composite:exclude;padding:1px}
.glass::after{content:'';position:absolute;inset:1px;border-radius:inherit;pointer-events:none;z-index:-1;background:transparent;opacity:.7}
.topbar{z-index:3;transform-origin:top center}.topbar:hover{transform:translateY(-1px);box-shadow:0 16px 34px rgba(16,42,67,.13),inset 0 1px 0 rgba(255,255,255,.9)}.topbar.compact{height:60px;margin-top:10px;border-radius:18px;background:transparent;}
.sidebar{background:transparent;box-shadow:inset -1px 0 rgba(176,249,250,.12)}
 .search{position:relative;margin:0;flex:0 1 42px;width:42px;min-width:42px;max-width:42px;overflow:hidden;transform-origin:left center;background:transparent;border-color:rgba(255,255,255,.58);box-shadow:inset 0 1px 0 rgba(255,255,255,.78),0 3px 10px rgba(16,42,67,.04);transition:flex-basis .48s cubic-bezier(.18,.9,.28,1.08),width .48s cubic-bezier(.18,.9,.28,1.08),max-width .48s cubic-bezier(.18,.9,.28,1.08),background .22s ease,box-shadow .22s ease}.search-trigger{display:grid;place-items:center;flex:none;width:100%;height:100%;padding:0;color:inherit;transform-origin:left center;transition:width .28s ease .1s,opacity .16s ease .12s,transform .22s cubic-bezier(.18,.9,.28,1.08)}.search-trigger span{font-size:20px;line-height:1;transition:transform .22s cubic-bezier(.18,.9,.28,1.08),opacity .16s ease}.search input{position:absolute;inset-inline:16px;width:calc(100% - 32px);min-width:0;border:0;outline:0;background:transparent;color:inherit;font:inherit;opacity:0;clip-path:inset(0 100% 0 0);pointer-events:none;transition:opacity .16s ease .1s,clip-path .3s ease .1s}.search input::placeholder{color:currentColor;opacity:1}.search.open{flex:1 1 auto;width:auto;max-width:100%}.search.open .search-trigger{width:0;opacity:0;transform:rotate(-12deg) scale(1.08);pointer-events:none}.search.open input{opacity:1;clip-path:inset(0 0 0 0);pointer-events:auto}
.search:hover{background:transparent;transform:translateY(-1px)}
.icon-button,.filter{transition:transform .2s cubic-bezier(.2,.8,.2,1),background .22s ease,box-shadow .22s ease}.icon-button:hover{transform:translateY(-1px)}.icon-button:active,.filter:active{transform:scale(.97)}
.filter{background:transparent;border:1px solid rgba(255,255,255,.55);box-shadow:inset 0 1px 0 rgba(255,255,255,.7),0 2px 6px rgba(16,42,67,.04)}.filter:hover{background:rgba(255,255,255,.68);transform:translateY(-1px)}.filter.selected{background:transparent;box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 5px 14px rgba(0,106,120,.12)}
.primary-button{position:relative;overflow:hidden;transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s ease,filter .2s ease}.primary-button::before{content:'';position:absolute;inset:0;background:transparent;pointer-events:none}.primary-button:hover{transform:translateY(-1px);box-shadow:0 11px 20px rgba(0,106,120,.26);filter:none}.primary-button:active{transform:scale(.975);box-shadow:0 4px 9px rgba(0,106,120,.18)}
.notifications-popover{background:transparent;box-shadow:0 22px 46px rgba(16,42,67,.18),inset 0 1px 0 rgba(255,255,255,.86);transform-origin:top right;animation:glass-expand .32s cubic-bezier(.2,.8,.2,1)}
.modal-backdrop{}.modal.glass{background:transparent;box-shadow:0 24px 64px rgba(16,42,67,.23),inset 0 1px 0 rgba(255,255,255,.92)}
 .modal input:focus,.modal select:focus,.modal textarea:focus,.search:focus-visible,.filter:focus-visible,.primary-button:focus-visible,.icon-button:focus-visible,.interactive-row:focus-visible,.outline-button:focus-visible,.mobile-nav button:focus-visible{outline:3px solid rgba(0,140,158,.42);outline-offset:3px}.interactive-row:hover{background:transparent;box-shadow:inset 0 1px 0 rgba(255,255,255,.72)}
 .topbar .condo-brand,.topbar .top-actions{overflow:hidden;max-width:100%;opacity:1;transform:translateX(0) scale(1);visibility:visible;transition:opacity .34s ease,transform .46s cubic-bezier(.18,.9,.28,1.08),max-width .46s cubic-bezier(.18,.9,.28,1.08),margin .46s cubic-bezier(.18,.9,.28,1.08),visibility 0s linear 0s}
@media(max-width:900px){.topbar.search-open{gap:0}.topbar.search-open .condo-brand,.topbar.search-open .top-actions{max-width:0;margin-left:0;margin-right:0;opacity:0;pointer-events:none;transform:translateX(-10px) scale(.96);visibility:hidden;transition:opacity .24s ease,transform .42s cubic-bezier(.18,.9,.28,1.08),max-width .46s cubic-bezier(.18,.9,.28,1.08),margin .46s cubic-bezier(.18,.9,.28,1.08),visibility 0s linear .46s}}
 @keyframes glass-expand{from{opacity:0;transform:translateY(-8px) scale(.94);}to{opacity:1;transform:translateY(0) scale(1);}}
@media(max-width:900px){.mobile-nav{overflow:visible;background:transparent;border:1px solid transparent;box-shadow:0 14px 34px rgba(16,42,67,.18),0 3px 10px rgba(0,106,120,.08),inset 0 1px 0 rgba(255,255,255,.82)}.mobile-nav button{z-index:1;min-width:54px}.mobile-nav button:hover{transform:translateY(-1px)}.mobile-nav button.selected{background:transparent;box-shadow:inset 0 1px 0 rgba(255,255,255,.95),0 5px 14px rgba(0,106,120,.13)}.mobile-nav button.selected::after{box-shadow:0 0 0 3px rgba(0,140,158,.14)}.topbar.compact{height:54px;margin-top:7px;border-radius:16px}}
 @media(prefers-reduced-motion:reduce){.glass,.primary-button,.filter,.search,.search-trigger,.search-trigger span,.search input,.icon-button,.mobile-nav button,.topbar .condo-brand,.topbar .top-actions{transition:none!important}.search.open input{width:100%;opacity:1;clip-path:none}.notifications-popover,.detail-modal{animation:none!important}}
/* Refinamento: fundo calmo; reflexos e gradientes ficam restritos ao material de navegação. */
.main-content{background:#f5fbfc}.welcome-banner{background:#e7f8f9}.notifications-popover.glass{position:fixed}
@media(max-width:900px){.mobile-nav.glass{position:fixed;display:grid;grid-template-columns:repeat(var(--mobile-nav-count),minmax(0,1fr));justify-content:initial;align-items:center;isolation:isolate}.mobile-nav button{width:100%;min-width:0;background:transparent!important;box-shadow:none!important}.mobile-nav button.selected{animation:none}.mobile-nav button.selected::after{display:none}.mobile-nav button span{position:relative}.mobile-nav button.selected .svg-icon{filter:invert(42%) sepia(97%) hue-rotate(142deg) brightness(89%) }.mobile-nav button:active{transform:scale(.97)} }
/* O modal é a superfície elevada; seu conteúdo não cria novos cards de vidro. */
.modal-backdrop{background:rgba(16,42,67,.24)}.modal.glass{background:rgba(252,254,255,.9);border-color:rgba(255,255,255,.88);box-shadow:0 24px 64px rgba(16,42,67,.2),inset 0 1px 0 rgba(255,255,255,.92);}.detail-list{gap:0;margin-top:20px;border-top:1px solid rgba(100,116,139,.16)}.detail-list div{background:transparent;padding:15px 0;border-radius:0;border-bottom:1px solid rgba(100,116,139,.16)}.detail-list div:nth-child(odd):not(.full){padding-right:16px}.detail-list div:nth-child(even):not(.full){padding-left:16px;border-left:1px solid rgba(100,116,139,.16)}.detail-list .full{padding-bottom:16px}.detail-list dt{color:#64748b}.detail-list dd{color:#102a43}.detail-list em{display:inline-flex}
@media(max-width:900px){.detail-list div:nth-child(even):not(.full){padding-left:0;border-left:0}.detail-list div:nth-child(odd):not(.full){padding-right:0}}
[data-theme="dark"] .modal-backdrop{background:rgba(2,19,31,.56)}[data-theme="dark"] .modal.glass{background:rgba(12,31,45,.88);border-color:rgba(181,235,239,.18);box-shadow:0 24px 64px rgba(0,0,0,.38),inset 0 1px 0 rgba(225,251,252,.14)}[data-theme="dark"] .modal.glass::before{opacity:.38}[data-theme="dark"] .detail-list{border-color:rgba(221,246,247,.16)}[data-theme="dark"] .detail-list div{border-color:rgba(221,246,247,.14)}[data-theme="dark"] .detail-list div:nth-child(even):not(.full){border-left-color:rgba(221,246,247,.14)}[data-theme="dark"] .detail-list dt{color:#b7c9d7}[data-theme="dark"] .detail-list dd{color:#f6fbfc}

/* Navegação é vidro contextual: sem preenchimento em gradiente fixo. */
.topbar.glass{background:rgba(249,254,254,.72)}
.topbar.compact{background:rgba(249,254,254,.8)}
@media(max-width:900px){.mobile-nav.glass{background:rgba(248,254,254,.58)}}
[data-theme="dark"] .topbar.glass{background:rgba(12,31,45,.72)}[data-theme="dark"] .topbar.compact{background:rgba(12,31,45,.8)}
@media(max-width:900px){[data-theme="dark"] .mobile-nav.glass{background:rgba(10,33,47,.64)}}

/* Movimento compartilhado: a navegação desktop recebe a mesma resposta física do mobile. */
@media(min-width:901px){
  .nav-item{position:relative;transition:color .24s ease,background .28s cubic-bezier(.2,.8,.2,1),transform .22s cubic-bezier(.2,.8,.2,1),box-shadow .28s ease}
  .nav-item:hover{transform:translateX(2px)}
  .nav-item:active{transform:translateX(1px) scale(.985)}
  .nav-item.selected{animation:desktop-nav-select .34s cubic-bezier(.2,.82,.25,1)}
  .nav-item .svg-icon{transition:transform .28s cubic-bezier(.2,.8,.2,1),filter .22s ease}
  .nav-item.selected .svg-icon{transform:scale(1.08)}
  .unit-card{transition:transform .22s cubic-bezier(.2,.8,.2,1),background .22s ease,box-shadow .22s ease}
  .unit-card:hover{transform:translateY(-1px)}.unit-card:active{transform:scale(.99)}
  .notifications-popover{animation:glass-expand .32s cubic-bezier(.2,.8,.2,1)}
}
@keyframes desktop-nav-select{from{transform:translateX(-3px) scale(.985);filter:none}to{transform:translateX(0) scale(1);filter:none}}
@keyframes material-rise{from{opacity:0;transform:translateY(8px) scale(.975);filter:blur(2px)}to{opacity:1;transform:translateY(0) scale(1);filter:blur(0)}}
@media(prefers-reduced-motion:reduce){.nav-item,.nav-item .svg-icon,.unit-card{transition:none!important}.nav-item.selected,.modal{animation:none!important}}
.occurrence-history{display:grid;gap:10px}.occurrence-history span{display:flex;justify-content:space-between;gap:16px}.occurrence-history small{color:var(--neutral);text-align:right}.occurrence-note{display:grid;gap:8px;margin-top:18px;font-size:12px;font-weight:700}.occurrence-note textarea{min-height:82px}.occurrence-actions{margin-top:16px}.occurrence-actions .primary-button{margin-left:auto;min-height:42px}.occurrence-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px}.occurrence-upload{position:relative;cursor:pointer}.occurrence-upload input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}.occurrence-attachments{margin-top:18px}.occurrence-attachment-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;margin-top:10px}.occurrence-attachment{display:grid;gap:5px;min-width:0;text-align:left;border:1px solid var(--line);border-radius:10px;padding:6px;background:transparent;color:var(--navy)}.occurrence-attachment img{width:100%;height:82px;object-fit:cover;border-radius:7px}.occurrence-attachment small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.occurrence-transfer{display:grid;gap:10px;margin-top:16px}.occurrence-transfer select{width:100%}
.users-toolbar{display:flex;align-items:center;gap:12px;padding:8px 0 16px;border-bottom:1px solid var(--line)}.users-search{flex:1;min-width:0;border:1px solid var(--line);border-radius:9px;padding:10px 12px;background:var(--bg);color:var(--navy);font-size:11px}.users-filters{border:0;padding:0}.user-row{gap:12px}.user-row>span:nth-child(2){flex:1;min-width:0}.user-avatar{flex:none;width:36px;height:36px;font-size:10px}.user-row em{font-size:9px;font-style:normal;border-radius:12px;padding:5px 8px;white-space:nowrap}.user-row .outline-button{padding:7px 10px;white-space:nowrap}.form-error{color:#b4232c!important;font-size:11px!important;margin:12px 0 0!important}
@media(max-width:900px){.users-toolbar{display:block}.users-filters{margin-top:10px}.user-row{align-items:flex-start;flex-wrap:wrap}.user-row>span:nth-child(2){min-width:calc(100% - 52px)}.user-row em{margin-left:48px}.user-row .outline-button{margin-left:0}.users-admin-panel{padding:8px 12px}}
.notifications-popover{max-height:min(480px,calc(100vh - 100px));display:flex;flex-direction:column;overflow:hidden}.popover-head{flex:none}.notifications-list{min-height:0;overflow-y:auto;overscroll-behavior:contain}@media(max-width:900px){.notifications-popover{max-height:calc(100vh - 88px)}}
/* Accessibility: dashboard action/status text meets WCAG AA in both themes. */
.primary-button{background:#007a89}
.panel-head button,.stat-card small{color:#006a78}
.stat-head i.red,.stat-card small.red,.red{color:#b4232c}
.stat-head i.blue,.stat-card small.blue,.blue{color:#1d5fa7}
.stat-head i.orange,.stat-card small.orange{color:#9a5b00}
/* CondoVale theme tokens and shared primitives. */
:root {
  color-scheme: light;
  --bg: #f3fafb;
  --surface: #ffffff;
  --surface-raised: #eaf5f7;
  --input: #ffffff;
  --navy: #102a43;
  --neutral: #64748b;
  --line: #d6e6ea;
  --primary: #008c9e;
  --secondary: #00afc1;
  --deep: #006a78;
  --soft: #e6f8f9;
  --sidebar: #092f38;
  --glass: rgba(255,255,255,.74);
  --glass-border: rgba(255,255,255,.86);
  --hover: #eaf5f7;
  --selected: #d9f3f4;
  --focus: rgba(0,175,193,.28);
  --danger-bg: #fff1f2;
  --danger-text: #b4232c;
  --success-bg: #eaf8ef;
  --success-text: #166534;
  --warning-bg: #fff5e6;
  --warning-text: #9a5b00;
}
[data-theme="dark"] {
  color-scheme: dark;
  --bg: #061f29;
  --surface: #103844;
  --surface-raised: #164653;
  --input: #092b36;
  --navy: #eaf6f8;
  --neutral: #a8c2cb;
  --line: #28515d;
  --primary: #00afc1;
  --secondary: #56d8df;
  --deep: #8de9e5;
  --soft: #123b4a;
  --sidebar: #061923;
  --glass: rgba(10,33,47,.72);
  --glass-border: rgba(181,235,239,.18);
  --hover: #164653;
  --selected: #1b5864;
  --focus: rgba(86,216,223,.32);
  --danger-bg: #3e242a;
  --danger-text: #ffb6bc;
  --success-bg: #164433;
  --success-text: #83e5bb;
  --warning-bg: #493a1e;
  --warning-text: #ffd879;
}
body { background: var(--bg); color: var(--navy); font-family: 'Inter','Plus Jakarta Sans',Arial,sans-serif; letter-spacing: -.005em; }
.main-content { background: var(--bg); }
.sidebar { background: var(--sidebar); color: #e6fffa; }
.glass { background: var(--glass); border-color: var(--glass-border); box-shadow: 0 12px 30px rgba(16,42,67,.10), 0 2px 8px rgba(0,106,120,.06), inset 0 1px 0 rgba(255,255,255,.18); backdrop-filter: blur(10px); }
.glass::before { background: linear-gradient(120deg, rgba(255,255,255,.24), transparent 44%); }
.glass::after { background: linear-gradient(135deg, rgba(255,255,255,.12), transparent 55%); }
.panel, .stat-card { background: var(--surface); border-color: var(--line); color: var(--navy); box-shadow: 0 6px 18px rgba(16,42,67,.055); }
.panel:hover, .stat-card:hover { box-shadow: 0 10px 24px rgba(16,42,67,.10); }
.search, .users-search, .modal input, .modal select, .modal textarea { background: var(--input); color: var(--navy); border-color: var(--line); }
.search input, .modal input::placeholder, .modal textarea::placeholder { color: var(--neutral); }
.search:focus-within, .modal input:focus, .modal select:focus, .modal textarea:focus, select:focus, textarea:focus { outline: 3px solid var(--focus); border-color: var(--secondary); }
.filter { background: var(--surface-raised); color: var(--neutral); border: 1px solid var(--line); box-shadow: none; }
.filter:hover { background: var(--hover); color: var(--navy); }
.filter.selected { background: var(--selected); color: var(--deep); border-color: var(--secondary); box-shadow: inset 0 0 0 1px var(--secondary); }
.filter:focus-visible, .primary-button:focus-visible, .outline-button:focus-visible, .icon-button:focus-visible, .nav-item:focus-visible, .theme-toggle:focus-visible { outline: 3px solid var(--focus); outline-offset: 3px; }
.filter:disabled, .primary-button:disabled, .outline-button:disabled { opacity: .55; cursor: not-allowed; }
.primary-button, .approve-action { background: var(--primary); color: #fff; box-shadow: 0 7px 15px rgba(0,140,158,.22); }
.primary-button:hover { background: var(--deep); }
.outline-button { background: transparent; border-color: var(--secondary); color: var(--deep); }
.outline-button:hover { background: var(--hover); }
.danger-action { background: var(--danger-bg); border-color: var(--danger-text); color: var(--danger-text); }
.danger-action:hover { filter: brightness(1.08); }
.modal-backdrop { background: rgba(2,19,31,.56); backdrop-filter: blur(4px); }
.modal, .modal.glass { background: var(--surface); border-color: var(--line); color: var(--navy); box-shadow: 0 24px 60px rgba(0,0,0,.30); }
.topbar.glass, .topbar.compact, .mobile-nav.glass, .notifications-popover { background: var(--glass); }
.theme-toggle { background: var(--surface-raised); border-color: var(--line); color: var(--navy); }
.theme-toggle:hover { background: var(--hover); color: var(--primary); }
.welcome-banner { background: var(--surface-raised); color: var(--navy); border: 1px solid var(--line); }
.welcome-banner p, .banner-logo small { color: var(--neutral); }
.detail-list, .detail-list div, .detail-list div:nth-child(even):not(.full) { border-color: var(--line); }
.detail-list dt { color: var(--neutral); }
.detail-list dd { color: var(--navy); }
.panel-head button, .stat-card small { color: var(--deep); }
.red { background: var(--danger-bg); color: var(--danger-text); }
.blue { background: var(--info-bg); color: var(--info-text); }
.yellow { background: var(--warning-bg); color: var(--warning-text); }
.green, .available { background: var(--success-bg); color: var(--success-text); }
.gray { background: var(--surface-raised); color: var(--neutral); }
.stat-head i.red { background: var(--danger-bg); color: var(--danger-text); }
.stat-head i.blue { background: var(--info-bg); color: var(--info-text); }
.stat-head i.orange { background: var(--warning-bg); color: var(--warning-text); }
.stat-card small.red, .stat-card small.blue, .stat-card small.orange { background: transparent; }
.stat-card small.red { color: var(--danger-text); }
.stat-card small.blue { color: var(--info-text); }
.stat-card small.orange { color: var(--warning-text); }
@media (max-width:430px) {
  .mobile-nav { justify-content:flex-start; gap:4px; overflow-x:auto; scrollbar-width:none; }
  .mobile-nav::-webkit-scrollbar { display:none; }
  .mobile-nav button { flex:0 0 48px; min-width:48px; font-size:0; }
  .mobile-nav button>span:first-child { font-size:17px; }
  .mobile-nav .mobile-nav-label { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
}
@media (max-width:460px) {
  .mobile-nav button { min-height:56px; }
  .mobile-nav button.selected .mobile-nav-label { position:static; width:auto; height:auto; padding:0; margin:0; overflow:visible; clip:auto; white-space:nowrap; border:0; font-size:9px; line-height:1.1; }
}
</style>
