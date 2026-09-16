<script setup lang="ts">
import type { CommonArea, Reservation } from '~/domain/reservation'
import { can, canViewModule, type UseCase } from '~/domain/permissions'

const active = ref('Início')
const showOccurrence = ref(false)
const occurrenceTitle = ref('')
const occurrenceCategory = ref('Manutenção')
const occurrenceDescription = ref('')
const submittingOccurrence = ref(false)
const reviewingReservation = ref(false)
const areas = ref<CommonArea[]>([])
const selectedAreaId = ref<string | null>(null)
const showNotifications = ref(false)
const detailTarget = ref<{ type: 'occurrence' | 'reservation' | 'notice'; id: string } | null>(null)
const isScrolled = ref(false)

const { user: authUser, loading: authLoading, error: authError, authenticate, restoreSession, logout } = useAuth()
const { public: runtimeConfig } = useRuntimeConfig()
const { users, loadUsers } = useUsers()
const { occurrences, loadOccurrences, createOccurrence } = useOccurrences()
const { reservations, loadReservations, listAreas, updateReservationStatus } = useReservations()
const { notices, loadNotices } = useNotices()

const navItems = [
  { label: 'Início', icon: 'home' }, { label: 'Ocorrências', icon: 'alert' },
  { label: 'Reservas', icon: 'calendar' }, { label: 'Comunicados', icon: 'message' }, { label: 'Relatórios', icon: 'building' },
] as const

function hasAccess(useCase: UseCase) {
  return authUser.value ? can(authUser.value.role, useCase) : false
}

const visibleNavItems = computed(() => navItems.filter(item => authUser.value ? canViewModule(authUser.value.role, item.label) : false))
const resident = computed(() => authUser.value?.role === 'resident' ? authUser.value : null)
const canCreateOccurrence = computed(() => authUser.value ? can(authUser.value.role, 'create-occurrence') : false)
const roleLabel = computed(() => ({ resident: 'Morador', employee: 'Funcionário', syndic: 'Síndico', admin: 'Administrador' }[authUser.value?.role ?? 'resident']))
const areaById = computed(() => new Map(areas.value.map(area => [area.id, area])))
const activeResidents = computed(() => users.value.filter(user => user.role === 'resident' && user.status === 'active'))
const openOccurrences = computed(() => occurrences.value.filter(item => !['completed', 'cancelled'].includes(item.status)))
const activeReservations = computed(() => reservations.value.filter(item => ['approved', 'pending'].includes(item.status)))
const residentName = computed(() => authUser.value?.name ?? 'Morador')
const residentInitials = computed(() => residentName.value.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase())

const stats = computed(() => [
  { label: 'Moradores', value: String(activeResidents.value.length), note: 'moradores ativos', icon: 'users' as const, tone: 'cyan' },
  { label: 'Ocorrências abertas', value: String(openOccurrences.value.length), note: 'em acompanhamento', icon: 'alert' as const, tone: 'red' },
  { label: 'Reservas ativas', value: String(activeReservations.value.length), note: 'pendentes ou aprovadas', icon: 'calendar' as const, tone: 'blue' },
  { label: 'Comunicados', value: String(notices.value.filter(item => item.status === 'published').length), note: 'publicados', icon: 'message' as const, tone: 'orange' },
])

const formatDate = (value: string) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(`${value}T12:00:00`))
const formatDateTime = (value: string) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value))
const occurrenceLabel = (status: string) => ({ open: 'Aberta', analysis: 'Em análise', assigned: 'Atribuída', in_progress: 'Em andamento', completed: 'Concluída', cancelled: 'Cancelada' }[status] ?? status)
const occurrenceTone = (status: string) => ({ open: 'red', analysis: 'blue', assigned: 'yellow', in_progress: 'blue', completed: 'green', cancelled: 'gray' }[status] ?? 'gray')
const reservationLabel = (status: Reservation['status']) => ({ approved: 'Aprovada', pending: 'Pendente', rejected: 'Recusada', cancelled: 'Cancelada' }[status])
const reservationTone = (status: Reservation['status']) => ({ approved: 'green', pending: 'yellow', rejected: 'red', cancelled: 'gray' }[status])

const recentOccurrences = computed(() => [...occurrences.value].sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)).slice(0, 3))
const upcomingReservations = computed(() => [...activeReservations.value].sort((a, b) => `${a.date}${a.startTime}`.localeCompare(`${b.date}${b.startTime}`)))
const filteredReservations = computed(() => selectedAreaId.value ? upcomingReservations.value.filter(item => item.areaId === selectedAreaId.value) : upcomingReservations.value)
const selectedOccurrence = computed(() => detailTarget.value?.type === 'occurrence' ? occurrences.value.find(item => item.id === detailTarget.value?.id) ?? null : null)
const selectedReservation = computed(() => detailTarget.value?.type === 'reservation' ? reservations.value.find(item => item.id === detailTarget.value?.id) ?? null : null)
const selectedNotice = computed(() => detailTarget.value?.type === 'notice' ? notices.value.find(item => item.id === detailTarget.value?.id) ?? null : null)
const recentNotices = computed(() => [...notices.value].sort((a, b) => b.publishedAt.localeCompare(a.publishedAt)).slice(0, 3))
const getArea = (areaId: string) => areaById.value.get(areaId)
const reservationMeta = (reservation: Reservation) => `${formatDate(reservation.date)} · ${reservation.startTime && reservation.endTime ? `${reservation.startTime}–${reservation.endTime}` : 'Dia inteiro'}`
const noticeMeta = (publishedAt: string, status: string) => status === 'published' ? `Publicado em ${formatDateTime(publishedAt)}` : 'Rascunho'
const noticeLabel = (status: string) => status === 'published' ? 'Publicado' : 'Rascunho'
const noticeTone = (status: string) => status === 'published' ? 'green' : 'gray'
const selectArea = (areaId: string | null) => { selectedAreaId.value = areaId }
const openDetail = (type: 'occurrence' | 'reservation' | 'notice', id: string) => { detailTarget.value = { type, id } }

async function loadDashboard() {
  const [areasResponse] = await Promise.all([listAreas(), loadUsers(), loadOccurrences(), loadReservations(), loadNotices()])
  areas.value = areasResponse.data
}

async function handleLogin(email: string, password: string) {
  await authenticate({ email, password })
  await loadDashboard()
}

async function handleLogout() {
  await logout()
  active.value = 'Início'
}

async function submitOccurrence() {
  if (!resident.value || !occurrenceTitle.value.trim() || !occurrenceDescription.value.trim()) return
  submittingOccurrence.value = true
  try {
    await createOccurrence({ title: occurrenceTitle.value.trim(), description: occurrenceDescription.value.trim(), category: occurrenceCategory.value, residentId: resident.value.id, unitId: resident.value.unitId })
    occurrenceTitle.value = ''
    occurrenceDescription.value = ''
    showOccurrence.value = false
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

function updateScrollMaterial() {
  isScrolled.value = window.scrollY > 24
}

onMounted(() => {
  restoreSession().then(user => user && loadDashboard()).catch(() => undefined)
  updateScrollMaterial()
  window.addEventListener('scroll', updateScrollMaterial, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('scroll', updateScrollMaterial))
const selectNav = (label: string) => { active.value = label }
</script>

<template>
  <LoginScreen v-if="!authUser" :loading="authLoading" :error="authError" :show-demo-access="runtimeConfig.dataSource !== 'api'" @submit="handleLogin" />
  <div v-else class="app-shell">
    <AppSidebar :active="active" :items="visibleNavItems" @select="selectNav" />

    <main class="main-content">
      <AppTopbar :name="residentName" :initials="residentInitials" :compact="isScrolled" @notifications="showNotifications = !showNotifications" @logout="handleLogout" />
      <aside v-if="showNotifications" class="notifications-popover glass"><div class="popover-head"><strong>Comunicados</strong><button aria-label="Fechar notificações" @click="showNotifications = false">×</button></div><div v-if="!recentNotices.length" class="empty-row">Nenhum comunicado novo.</div><button v-for="notice in recentNotices" :key="notice.id" class="notification-item" @click="active = 'Comunicados'; showNotifications = false"><SvgIcon name="message" /><span><strong>{{ notice.title }}</strong><small>{{ noticeMeta(notice.publishedAt, notice.status) }}</small></span></button></aside>

      <div class="page-wrap">
        <section class="page-heading">
          <div><div class="eyebrow">{{ roleLabel }} · Residencial Vale Verde</div><h1>Olá, {{ residentName }}!</h1><p>Veja o que está acontecendo no seu condomínio hoje.</p></div>
          <button v-if="hasAccess('view-notices')" class="primary-button" @click="active = 'Comunicados'"><SvgIcon name="message" />Ver comunicados</button>
        </section>

        <div v-if="active === 'Início'">
          <section class="stat-grid"><DashboardStat v-for="stat in stats" :key="stat.label" v-bind="stat" /></section>
          <section class="content-grid three-columns">
            <article class="panel"><div class="panel-head"><h2>Ocorrências recentes</h2><button @click="active = 'Ocorrências'">Ver todas</button></div><div class="list"><div v-if="!recentOccurrences.length" class="empty-row">Nenhuma ocorrência registrada.</div><button v-for="item in recentOccurrences" :key="item.id" class="list-row interactive-row" @click="openDetail('occurrence', item.id)"><span class="dot teal"></span><span><strong>{{ item.title }}</strong><small>{{ item.category }} · {{ formatDateTime(item.createdAt) }}</small></span><em :class="occurrenceTone(item.status)">{{ occurrenceLabel(item.status) }}</em></button></div></article>
            <article v-if="hasAccess('view-common-areas') || hasAccess('manage-reservations')" class="panel"><div class="panel-head"><h2>Próximas reservas</h2><button @click="active = 'Reservas'">Ver agenda</button></div><div class="list"><div v-if="!upcomingReservations.length" class="empty-row">Nenhuma reserva ativa.</div><button v-for="item in upcomingReservations.slice(0, 3)" :key="item.id" class="list-row interactive-row" @click="openDetail('reservation', item.id)"><span class="row-icon"><SvgIcon name="calendar" /></span><span><strong>{{ getArea(item.areaId)?.name ?? 'Área comum' }}</strong><small>{{ reservationMeta(item) }}</small></span><em :class="reservationTone(item.status)">{{ reservationLabel(item.status) }}</em></button></div></article>
            <article v-if="hasAccess('view-notices') || hasAccess('publish-notices')" class="panel"><div class="panel-head"><h2>Comunicados recentes</h2><button @click="active = 'Comunicados'">Ver todos</button></div><div class="list"><div v-if="!recentNotices.length" class="empty-row">Nenhum comunicado publicado.</div><button v-for="item in recentNotices" :key="item.id" class="list-row interactive-row" @click="openDetail('notice', item.id)"><span class="row-icon"><SvgIcon name="message" /></span><span><strong>{{ item.title }}</strong><small>{{ noticeMeta(item.publishedAt, item.status) }}</small></span><em :class="noticeTone(item.status)">{{ noticeLabel(item.status) }}</em></button></div></article>
          </section>
          <section class="welcome-banner"><div><h2>Condomínio melhor <span>quando as pessoas se conectam.</span></h2><p>Participe, registre, reserve e acompanhe. Tudo em um só lugar.</p></div><div class="people-illustration"><SvgIcon name="users" /><SvgIcon name="users" /><SvgIcon name="users" /></div><div class="banner-logo"><SvgIcon name="building" /><small>CondoVale</small></div></section>
        </div>

        <ReportPage v-else-if="active === 'Relatórios' && hasAccess('generate-reports')" :occurrences="occurrences" />
        <ReservationPage v-else-if="active === 'Reservas'" :areas="areas" :reservations="reservations" :resident-id="resident?.id" :can-manage="hasAccess('approve-or-reject-reservation')" @reservation-created="loadReservations" @reservation-cancelled="loadReservations" @open-details="openDetail('reservation', $event)" />
        <div v-else>
          <section class="section-intro"><div class="section-icon"><SvgIcon :name="active === 'Reservas' ? 'calendar' : active === 'Ocorrências' ? 'alert' : 'message'" /></div><div><h2>{{ active }}</h2><p>{{ active === 'Reservas' ? 'Agende e acompanhe os espaços do condomínio.' : active === 'Ocorrências' ? 'Registre solicitações e acompanhe cada atendimento.' : 'Informação importante para viver melhor em comunidade.' }}</p></div><button v-if="active === 'Ocorrências' && canCreateOccurrence" class="primary-button" @click="showOccurrence = true">Nova ocorrência</button></section>
          <article class="panel detail-panel" v-if="active === 'Comunicados'"><div v-if="!recentNotices.length" class="empty-row">Nenhum comunicado publicado.</div><button v-for="item in recentNotices" :key="item.id" class="detail-row interactive-row" @click="openDetail('notice', item.id)"><span class="row-icon large"><SvgIcon name="message" /></span><span><strong>{{ item.title }}</strong><small>{{ noticeMeta(item.publishedAt, item.status) }}</small></span><em :class="noticeTone(item.status)">{{ noticeLabel(item.status) }}</em><span class="arrow"></span></button></article>
          <article class="panel detail-panel" v-else><div v-if="!recentOccurrences.length" class="empty-row">Nenhuma ocorrência registrada.</div><button v-for="item in recentOccurrences" :key="item.id" class="detail-row interactive-row" @click="openDetail('occurrence', item.id)"><span class="row-icon large"><SvgIcon name="alert" /></span><span><strong>{{ item.title }}</strong><small>{{ item.category }} · {{ formatDateTime(item.createdAt) }}</small></span><em :class="occurrenceTone(item.status)">{{ occurrenceLabel(item.status) }}</em><span class="arrow"></span></button></article>
        </div>
      </div>
    </main>

    <MobileNav :active="active" :items="visibleNavItems" @select="selectNav" />
    <div v-if="showOccurrence" class="modal-backdrop" @click.self="showOccurrence = false"><div class="modal glass"><button class="modal-close" @click="showOccurrence = false" aria-label="Fechar"></button><div class="section-icon"><SvgIcon name="alert" /></div><h2>Abrir ocorrência</h2><p>Conte para a gente o que está acontecendo.</p><label>Título<input v-model="occurrenceTitle" placeholder="Ex.: Vazamento na garagem" /></label><label>Tipo de ocorrência<select v-model="occurrenceCategory"><option>Manutenção</option><option>Convivência</option><option>Segurança</option></select></label><label>Descrição<textarea v-model="occurrenceDescription" placeholder="Descreva a ocorrência..."></textarea></label><div class="modal-actions"><button class="outline-button" @click="showOccurrence = false">Cancelar</button><button class="primary-button" :disabled="submittingOccurrence || !resident" @click="submitOccurrence">{{ submittingOccurrence ? 'Enviando...' : 'Registrar ocorrência' }}</button></div></div></div>
    <DetailModal v-if="selectedOccurrence" title="Detalhes da ocorrência" icon="alert" @close="detailTarget = null"><dl class="detail-list"><div><dt>Status</dt><dd><em :class="occurrenceTone(selectedOccurrence.status)">{{ occurrenceLabel(selectedOccurrence.status) }}</em></dd></div><div><dt>Categoria</dt><dd>{{ selectedOccurrence.category }}</dd></div><div><dt>Registrada em</dt><dd>{{ formatDateTime(selectedOccurrence.createdAt) }}</dd></div><div class="full"><dt>Descrição</dt><dd>{{ selectedOccurrence.description }}</dd></div></dl></DetailModal>
    <DetailModal v-if="selectedReservation" title="Detalhes da reserva" icon="calendar" @close="detailTarget = null"><dl class="detail-list"><div><dt>Área comum</dt><dd>{{ getArea(selectedReservation.areaId)?.name ?? 'Área comum' }}</dd></div><div><dt>Status</dt><dd><em :class="reservationTone(selectedReservation.status)">{{ reservationLabel(selectedReservation.status) }}</em></dd></div><div><dt>Horário</dt><dd>{{ reservationMeta(selectedReservation) }}</dd></div><div><dt>Capacidade</dt><dd>{{ getArea(selectedReservation.areaId)?.capacity ?? 'Não informada' }} pessoas</dd></div><div class="full"><dt>Solicitada em</dt><dd>{{ formatDateTime(selectedReservation.createdAt) }}</dd></div></dl><div v-if="hasAccess('approve-or-reject-reservation') && selectedReservation.status === 'pending'" class="modal-actions reservation-review-actions"><button class="outline-button danger-action" :disabled="reviewingReservation" @click="reviewReservation('rejected')"><SvgIcon name="reject" />Reprovar</button><button class="primary-button approve-action" :disabled="reviewingReservation" @click="reviewReservation('approved')"><SvgIcon name="approve" />{{ reviewingReservation ? 'Salvando...' : 'Aprovar reserva' }}</button></div></DetailModal>
    <DetailModal v-if="selectedNotice" title="Detalhes do comunicado" icon="message" @close="detailTarget = null"><dl class="detail-list"><div><dt>Status</dt><dd><em :class="noticeTone(selectedNotice.status)">{{ noticeLabel(selectedNotice.status) }}</em></dd></div><div><dt>Publicado em</dt><dd>{{ formatDateTime(selectedNotice.publishedAt) }}</dd></div><div class="full"><dt>Mensagem</dt><dd>{{ selectedNotice.content }}</dd></div></dl></DetailModal>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');
:root{--primary:#008c9e;--secondary:#00afc1;--deep:#006a78;--soft:#ddf6f7;--navy:#102a43;--neutral:#64748b;--line:#e2edf0;--bg:#f4fbfc}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--navy);font-family:Inter,Arial,sans-serif}button{font:inherit;cursor:pointer;border:0;background:none;color:inherit}.app-shell{min-height:100vh;display:flex}.sidebar{width:242px;background:#092f38;color:#d5f5f5;display:flex;flex-direction:column;padding:28px 16px 22px;position:fixed;inset:0 auto 0 0}.brand{font:800 20px 'Plus Jakarta Sans';display:flex;gap:8px;align-items:center;color:#fff}.brand>span:last-child>span,.mobile-brand>span:last-child{color:#24c5cf}.brand-mark{font-size:29px;color:#15bdc9;line-height:1}.brand-tag{font-size:7px;letter-spacing:1.7px;margin:3px 0 36px 40px;color:#9bd9dd}.nav-item{width:100%;display:flex;align-items:center;gap:14px;color:#a7c4c7;font-size:12px;padding:12px 14px;border-radius:9px;text-align:left;margin:3px 0}.nav-item.selected{background:var(--primary);color:#fff;box-shadow:0 8px 20px #001c2140}.nav-icon{width:17px;text-align:center;font-size:16px}.sidebar-spacer{flex:1}.unit-card{display:flex;align-items:center;gap:9px;background:#12424a;padding:11px;border-radius:11px;font-size:10px}.unit-card strong,.unit-card small{display:block}.unit-card small{font-size:8px;color:#9bc5c8;margin-top:3px}.unit-card span{margin-left:auto;font-size:22px}.unit-photo{background:#56a7a6;color:white;border-radius:6px;padding:8px 5px}.sidebar-foot{font-size:10px;color:#91b9bd;display:flex;justify-content:space-between;margin-top:20px;padding:0 8px}.main-content{margin-left:242px;width:calc(100% - 242px);min-height:100vh;background:linear-gradient(140deg,#f7fcfd 0%,#eff9fa 100%)}.topbar{height:76px;margin:15px 26px 0;padding:13px 20px;border-radius:22px;display:flex;align-items:center;gap:24px}.glass{background:rgba(255,255,255,.78);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.9);box-shadow:0 8px 30px rgba(16,42,67,.07)}.search{height:42px;display:flex;align-items:center;gap:12px;background:#f4fafb;border:1px solid var(--line);border-radius:12px;padding:0 16px;color:var(--neutral);font-size:13px;flex:1;max-width:430px}.top-actions{display:flex;align-items:center;gap:12px;margin-left:auto}.icon-button{position:relative;font-size:20px;color:var(--neutral)}.icon-button b{position:absolute;background:#ed5d66;color:#fff;border-radius:10px;font-size:9px;padding:2px 5px;top:-5px;right:-7px}.avatar{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--soft);color:var(--deep);font-size:11px;font-weight:700}.greeting small,.greeting strong{display:block;font-size:10px}.greeting strong{font-size:12px;margin-top:3px}.page-wrap{padding:28px 30px 50px;max-width:1500px;margin:auto}.page-heading{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:24px}.eyebrow{font-size:10px;color:var(--neutral);margin-bottom:8px}h1,h2,p{margin:0}h1{font:800 28px 'Plus Jakarta Sans';letter-spacing:-.7px}h1 span{font-size:23px}.page-heading p{font-size:12px;color:var(--neutral);margin-top:5px}.primary-button{background:var(--primary);color:#fff;border-radius:10px;padding:11px 16px;font-size:12px;font-weight:700;box-shadow:0 7px 15px #008c9e2b}.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}.stat-card,.panel{background:#fff;border:1px solid #e6eff1;border-radius:15px;box-shadow:0 6px 18px rgba(16,42,67,.055)}.stat-card{padding:17px 18px}.stat-head{display:flex;align-items:center;justify-content:space-between;color:var(--neutral);font-size:11px;margin-bottom:17px}.stat-head i{font-style:normal;display:grid;place-items:center;width:29px;height:29px;border-radius:9px;background:var(--soft);color:var(--primary);font-weight:700}.stat-head i.red,.stat-card small.red{color:#e6575f}.stat-head i.red{background:#ffedef}.stat-head i.orange,.stat-card small.orange{color:#e99028}.stat-head i.orange{background:#fff5e6}.stat-head i.blue,.stat-card small.blue{color:#2c82d7}.stat-head i.blue{background:#eaf4ff}.stat-card>strong{font:700 25px 'Plus Jakarta Sans';display:block}.stat-card small{display:block;color:var(--primary);font-size:10px;margin-top:8px}.content-grid{display:grid;gap:16px}.three-columns{grid-template-columns:repeat(3,1fr)}.panel{padding:17px 18px}.panel-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.panel-head h2{font:700 14px 'Plus Jakarta Sans'}.panel-head button{font-size:10px;color:var(--primary);font-weight:600}.list-row{display:flex;align-items:center;gap:10px;border-top:1px solid #edf2f3;padding:13px 0;min-height:56px}.list-row:first-child{border-top:0;padding-top:4px}.list-row>div,.detail-row>div:nth-child(2){flex:1}.list-row strong,.list-row small,.detail-row strong,.detail-row small{display:block}.list-row strong,.detail-row strong{font-size:11px;font-weight:600}.list-row small,.detail-row small{font-size:9px;color:var(--neutral);margin-top:4px}.dot{width:7px;height:7px;border-radius:50%;background:var(--primary)}.row-icon{display:grid;place-items:center;width:28px;height:28px;border-radius:50%;background:var(--soft);color:var(--primary);font-size:14px}.row-icon.large{width:38px;height:38px}.arrow{font-size:20px;color:#a7bbc1}.list-row em,.detail-row em{font-size:8px;font-style:normal;border-radius:12px;padding:4px 7px;white-space:nowrap}.red{color:#ef626c;background:#ffedef}.yellow{color:#b77919;background:#fff6dd}.blue{color:#2d82cd;background:#e9f4ff}.green{color:#159d68;background:#e5faef}.gray{color:#718096;background:#f1f4f5}.welcome-banner{margin-top:20px;padding:20px 26px;border-radius:17px;background:linear-gradient(100deg,#d9f6f7,#edfafa);display:flex;align-items:center;gap:24px;overflow:hidden}.welcome-banner h2{font:700 18px 'Plus Jakarta Sans'}.welcome-banner h2 span{color:var(--primary)}.welcome-banner p{font-size:11px;color:var(--neutral);margin-top:6px}.people-illustration{font-size:42px;color:var(--primary);margin-left:auto}.banner-logo{text-align:center;color:var(--primary);font-size:35px;font-weight:700}.banner-logo small{font-size:11px;color:var(--navy)}.section-intro{display:flex;align-items:center;gap:16px;margin-bottom:20px}.section-intro h2{font:700 24px 'Plus Jakarta Sans'}.section-intro p{font-size:12px;color:var(--neutral);margin-top:4px}.section-intro .primary-button{margin-left:auto}.section-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:var(--soft);color:var(--primary);font-weight:800;font-size:20px}.detail-panel{padding:9px 20px}.filter-row{display:flex;gap:8px;padding:8px 0 16px;border-bottom:1px solid var(--line)}.filter{padding:8px 12px;border-radius:9px;background:#f4f8f9;color:var(--neutral);font-size:10px}.filter.selected{background:var(--soft);color:var(--deep);font-weight:700}.detail-row{display:flex;align-items:center;gap:15px;padding:16px 4px;border-bottom:1px solid var(--line)}.detail-row:last-child{border:0}.place-thumb{width:50px;height:38px;border-radius:8px;display:grid;place-items:center;background:#d7eff0;color:var(--primary);font-size:20px}.available{font-size:10px;color:#159d68;background:#e5faef;border-radius:12px;padding:5px 8px}.outline-button{border:1px solid #9cdae0;color:var(--deep);border-radius:9px;padding:9px 13px;font-size:10px;font-weight:700}.mobile-brand,.mobile-nav{display:none}.modal-backdrop{position:fixed;inset:0;background:#102a4366;display:grid;place-items:center;z-index:5;padding:20px}.modal{width:min(440px,100%);padding:28px;border-radius:24px;position:relative}.modal h2{font:700 22px 'Plus Jakarta Sans';margin-top:16px}.modal p{font-size:12px;color:var(--neutral);margin:5px 0 22px}.modal-close{position:absolute;right:18px;top:14px;font-size:24px;color:var(--neutral)}label{font-size:11px;font-weight:600;display:block;margin-top:14px}select,textarea{font:inherit;width:100%;margin-top:7px;border:1px solid var(--line);border-radius:9px;padding:11px;background:#fbfefe;color:var(--navy);font-size:11px}textarea{min-height:90px;resize:vertical}.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}
@media(max-width:900px){.sidebar{display:none}.main-content{margin-left:0;width:100%;padding-bottom:76px}.topbar{margin:10px 12px 0;height:62px;padding:10px 14px;border-radius:18px}.mobile-brand{display:block;font:800 15px 'Plus Jakarta Sans';white-space:nowrap}.mobile-brand .brand-mark{font-size:21px}.search{max-width:none;height:38px;padding:0 11px}.search span{display:none}.greeting{display:none}.page-wrap{padding:22px 14px}.page-heading{display:block}.page-heading h1{font-size:24px}.page-heading .primary-button{width:100%;margin-top:16px}.eyebrow{font-size:9px}.stat-grid{grid-template-columns:repeat(2,1fr);gap:10px}.stat-card{padding:14px}.stat-head{font-size:9px;margin-bottom:13px}.stat-card>strong{font-size:21px}.three-columns{grid-template-columns:1fr}.content-grid{gap:12px}.welcome-banner{display:none}.section-intro{align-items:flex-start;flex-wrap:wrap}.section-intro .primary-button{width:100%;margin-left:0}.section-intro h2{font-size:20px}.detail-panel{padding:8px 12px}.filter-row{overflow:auto;white-space:nowrap}.detail-row{gap:10px}.detail-row .outline-button{padding:8px}.mobile-nav{position:fixed;display:flex;z-index:4;bottom:10px;left:10px;right:10px;justify-content:space-around;padding:8px 4px;border-radius:19px}.mobile-nav button{display:flex;flex-direction:column;align-items:center;gap:3px;font-size:8px;color:var(--neutral);padding:4px 6px}.mobile-nav button span{font-size:17px}.mobile-nav button.selected{color:var(--primary);font-weight:700}.top-actions{gap:8px}.avatar{width:32px;height:32px}.icon-button{font-size:17px}}
.condo-brand{font:800 20px 'Plus Jakarta Sans';display:flex;gap:8px;align-items:center;color:#fff;position:relative}.condo-brand .brand-name span{color:#24c5cf}.condo-brand small{position:absolute;top:28px;left:38px;font:7px Inter,sans-serif;letter-spacing:1.7px;color:#9bd9dd;white-space:nowrap}.brand-mark{display:grid;place-items:center}.brand-mark .svg-icon{width:27px;height:27px}.sidebar nav{margin-top:47px}.topbar .condo-brand{display:none}.svg-icon{display:block;width:1em;height:1em;object-fit:contain;filter:invert(42%) sepia(97%) saturate(885%) hue-rotate(142deg) brightness(89%) contrast(101%)}.nav-icon .svg-icon{width:17px;height:17px;filter:invert(86%) sepia(15%) saturate(412%) hue-rotate(131deg) brightness(84%) contrast(82%)}.nav-item.selected .svg-icon,.primary-button .svg-icon{filter:brightness(0) invert(1)}.primary-button{display:inline-flex;align-items:center;gap:7px}.primary-button:disabled{opacity:.55;cursor:not-allowed}.primary-button .svg-icon{width:14px;height:14px}.row-icon .svg-icon,.section-icon .svg-icon{width:16px;height:16px}.row-icon.large .svg-icon{width:20px;height:20px}.stat-head i .svg-icon{width:15px;height:15px}.stat-head i.red .svg-icon{filter:invert(42%) sepia(58%) saturate(1097%) hue-rotate(314deg) brightness(96%) contrast(94%)}.stat-head i.orange .svg-icon{filter:invert(62%) sepia(56%) saturate(631%) hue-rotate(353deg) brightness(98%) contrast(87%)}.stat-head i.blue .svg-icon{filter:invert(50%) sepia(45%) saturate(1042%) hue-rotate(171deg) brightness(89%) contrast(91%)}.icon-button .svg-icon{width:19px;height:19px}.unit-photo .svg-icon{width:16px;height:16px;filter:brightness(0) invert(1)}.people-illustration{display:flex;gap:12px}.people-illustration .svg-icon{width:36px;height:36px}.banner-logo .svg-icon{width:35px;height:35px;margin:auto}.place-thumb .svg-icon{width:21px;height:21px}.arrow,.action-arrow,.chevron{display:inline-block;width:7px;height:7px;border-top:1.5px solid currentColor;border-right:1.5px solid currentColor;transform:rotate(45deg);flex:none}.action-arrow{margin-left:7px;width:5px;height:5px}.more-icon{width:15px;height:10px;border-top:2px solid currentColor;border-bottom:2px solid currentColor;position:relative}.more-icon::after,.modal-close::before,.modal-close::after{content:'';position:absolute;background:currentColor}.modal-close{width:20px;height:20px}.modal-close::before,.modal-close::after{width:14px;height:1.5px;top:9px;left:3px;transform:rotate(45deg)}.modal-close::after{transform:rotate(-45deg)}.empty-row{color:var(--neutral);font-size:11px;padding:18px 0;text-align:center}.modal input,.modal select,.modal textarea{font:inherit;width:100%;margin-top:7px;border:1px solid var(--line);border-radius:9px;padding:11px;background:#fbfefe;color:var(--navy);font-size:11px}.notifications-popover{position:fixed;z-index:6;top:82px;right:30px;width:min(330px,calc(100vw - 28px));padding:12px;border-radius:18px}.popover-head{display:flex;align-items:center;justify-content:space-between;padding:4px 5px 10px;font-size:13px}.popover-head button{font-size:20px;color:var(--neutral)}.notification-item{width:100%;display:flex;gap:10px;text-align:left;padding:12px 6px;border-top:1px solid var(--line)}.notification-item .svg-icon{width:17px;height:17px;flex:none;margin-top:2px}.notification-item span{display:grid;gap:3px}.notification-item strong{font-size:11px}.notification-item small{font-size:9px;color:var(--neutral)}.interactive-row{width:100%;text-align:left;transition:background .18s ease,transform .18s ease}.interactive-row:hover{background:#f5fbfc}.interactive-row:active{transform:scale(.992)}.interactive-row>span:nth-child(2){flex:1;min-width:0}.interactive-row>span:nth-child(2) strong,.interactive-row>span:nth-child(2) small{display:block}.detail-modal{animation:modal-in .22s cubic-bezier(.2,.8,.2,1)}.detail-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:22px 0 0}.detail-list div{padding:12px;border-radius:11px;background:rgba(221,246,247,.52)}.detail-list .full{grid-column:1/-1}.detail-list dt{font-size:9px;color:var(--neutral);margin-bottom:5px}.detail-list dd{margin:0;font-size:12px;line-height:1.5}.detail-list em{font-size:9px;font-style:normal;border-radius:12px;padding:4px 7px}.reservation-details{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:12px 0;padding:12px;border-radius:10px;background:var(--soft)}.reservation-details strong,.reservation-details small{display:block}.reservation-details strong{font-size:11px}.reservation-details small{font-size:9px;color:var(--neutral);margin-top:3px}@keyframes modal-in{from{opacity:0;transform:translateY(10px) scale(.985)}to{opacity:1;transform:translateY(0) scale(1)}}@media(max-width:900px){.topbar .condo-brand{display:flex;font-size:15px;white-space:nowrap}.topbar .condo-brand .brand-mark{font-size:21px}.notifications-popover{top:72px;right:14px}.reservation-details{align-items:flex-start;flex-direction:column}.mobile-nav{bottom:12px;left:12px;right:12px;padding:7px 8px;border:1px solid rgba(255,255,255,.8);background:rgba(223,246,247,.72);backdrop-filter:blur(20px) saturate(140%);box-shadow:0 12px 32px rgba(16,42,67,.16),inset 0 1px 0 rgba(255,255,255,.85);isolation:isolate}.mobile-nav::before{content:'';position:absolute;inset:1px;border-radius:18px;background:linear-gradient(115deg,rgba(255,255,255,.62),rgba(255,255,255,0) 45%);pointer-events:none;z-index:-1}.mobile-nav button{position:relative;min-width:50px;border-radius:13px;transition:color .2s ease,transform .2s ease,background .25s ease}.mobile-nav button:active{transform:scale(.91)}.mobile-nav button.selected{background:rgba(255,255,255,.62);box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 3px 10px rgba(0,106,120,.12);animation:liquid-select .28s cubic-bezier(.2,.8,.2,1)}.mobile-nav button.selected::after{content:'';position:absolute;bottom:1px;width:4px;height:4px;border-radius:50%;background:var(--primary);box-shadow:0 0 0 3px rgba(0,140,158,.12)}.mobile-nav .svg-icon{width:17px;height:17px;transition:transform .25s ease,filter .2s ease}.mobile-nav button.selected .svg-icon{transform:translateY(-1px) scale(1.08)}.detail-list{grid-template-columns:1fr}}@keyframes liquid-select{0%{transform:scale(.88);filter:brightness(1.08)}70%{transform:scale(1.04)}100%{transform:scale(1)}}@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
/* Liquid Glass material: reservado para navegação, controles e superfícies elevadas. */
.main-content{background:radial-gradient(circle at 78% 2%,rgba(0,175,193,.15),transparent 25rem),radial-gradient(circle at 30% 76%,rgba(221,246,247,.92),transparent 30rem),linear-gradient(145deg,#f9fdfd,#edf8f9)}
.glass{position:relative;isolation:isolate;overflow:hidden;background:linear-gradient(128deg,rgba(255,255,255,.58),rgba(221,246,247,.34) 46%,rgba(255,255,255,.42));border:1px solid transparent;background-clip:padding-box;backdrop-filter:blur(22px) saturate(145%) contrast(104%);box-shadow:0 12px 30px rgba(16,42,67,.10),0 2px 8px rgba(0,106,120,.06),inset 0 1px 0 rgba(255,255,255,.72);transition:transform .28s cubic-bezier(.2,.8,.2,1),box-shadow .28s cubic-bezier(.2,.8,.2,1),background .28s ease,backdrop-filter .28s ease,border-radius .28s cubic-bezier(.2,.8,.2,1)}
.glass::before{content:'';position:absolute;inset:0;border-radius:inherit;pointer-events:none;z-index:-1;background:radial-gradient(130% 92% at 9% 0%,rgba(255,255,255,.76),rgba(255,255,255,0) 42%),linear-gradient(112deg,rgba(255,255,255,.34),transparent 24%,rgba(0,140,158,.06) 76%,rgba(255,255,255,.38));mask:linear-gradient(#000,#000) padding-box,linear-gradient(#000,#000);mask-composite:exclude;padding:1px}
.glass::after{content:'';position:absolute;inset:1px;border-radius:inherit;pointer-events:none;z-index:-1;background:linear-gradient(180deg,rgba(255,255,255,.42),rgba(255,255,255,0) 28%);opacity:.7}
.topbar{z-index:3;transform-origin:top center}.topbar:hover{transform:translateY(-1px);box-shadow:0 16px 34px rgba(16,42,67,.13),inset 0 1px 0 rgba(255,255,255,.9)}.topbar.compact{height:60px;margin-top:10px;border-radius:18px;background:linear-gradient(128deg,rgba(255,255,255,.68),rgba(221,246,247,.42));backdrop-filter:blur(26px) saturate(155%)}
.sidebar{background:linear-gradient(160deg,rgba(5,49,59,.96),rgba(8,72,79,.84));backdrop-filter:blur(18px) saturate(125%);box-shadow:inset -1px 0 rgba(176,249,250,.12)}
.search{position:relative;background:linear-gradient(115deg,rgba(255,255,255,.48),rgba(221,246,247,.3));border-color:rgba(255,255,255,.58);backdrop-filter:blur(14px) saturate(135%);box-shadow:inset 0 1px 0 rgba(255,255,255,.78),0 3px 10px rgba(16,42,67,.04);transition:background .22s ease,box-shadow .22s ease,transform .22s cubic-bezier(.2,.8,.2,1)}
.search:hover{background:linear-gradient(115deg,rgba(255,255,255,.7),rgba(221,246,247,.45));transform:translateY(-1px)}
.icon-button,.filter{transition:transform .2s cubic-bezier(.2,.8,.2,1),background .22s ease,box-shadow .22s ease}.icon-button:hover{transform:translateY(-1px)}.icon-button:active,.filter:active{transform:scale(.97)}
.filter{background:linear-gradient(120deg,rgba(255,255,255,.42),rgba(221,246,247,.28));border:1px solid rgba(255,255,255,.55);backdrop-filter:blur(12px) saturate(130%);box-shadow:inset 0 1px 0 rgba(255,255,255,.7),0 2px 6px rgba(16,42,67,.04)}.filter:hover{background:rgba(255,255,255,.68);transform:translateY(-1px)}.filter.selected{background:linear-gradient(120deg,rgba(221,246,247,.78),rgba(255,255,255,.62));box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 5px 14px rgba(0,106,120,.12)}
.primary-button{position:relative;overflow:hidden;transition:transform .2s cubic-bezier(.2,.8,.2,1),box-shadow .2s ease,filter .2s ease}.primary-button::before{content:'';position:absolute;inset:0;background:linear-gradient(120deg,rgba(255,255,255,.22),transparent 32%,rgba(0,50,60,.10));pointer-events:none}.primary-button:hover{transform:translateY(-1px);box-shadow:0 11px 20px rgba(0,106,120,.26);filter:saturate(1.05)}.primary-button:active{transform:scale(.975);box-shadow:0 4px 9px rgba(0,106,120,.18)}
.notifications-popover{background:linear-gradient(135deg,rgba(255,255,255,.66),rgba(221,246,247,.48));backdrop-filter:blur(28px) saturate(155%);box-shadow:0 22px 46px rgba(16,42,67,.18),inset 0 1px 0 rgba(255,255,255,.86);transform-origin:top right;animation:glass-expand .32s cubic-bezier(.2,.8,.2,1)}
.modal-backdrop{backdrop-filter:blur(4px) saturate(115%)}.modal.glass{background:linear-gradient(138deg,rgba(255,255,255,.75),rgba(221,246,247,.53) 58%,rgba(255,255,255,.61));backdrop-filter:blur(30px) saturate(155%);box-shadow:0 24px 64px rgba(16,42,67,.23),inset 0 1px 0 rgba(255,255,255,.92)}
.modal input:focus,.modal select:focus,.modal textarea:focus,.search:focus-within,.filter:focus-visible,.primary-button:focus-visible,.icon-button:focus-visible,.interactive-row:focus-visible,.outline-button:focus-visible,.mobile-nav button:focus-visible{outline:3px solid rgba(0,140,158,.42);outline-offset:3px}.interactive-row:hover{background:linear-gradient(100deg,rgba(221,246,247,.46),rgba(255,255,255,.5));box-shadow:inset 0 1px 0 rgba(255,255,255,.72)}
@keyframes glass-expand{from{opacity:0;transform:translateY(-8px) scale(.94);backdrop-filter:blur(10px) saturate(110%)}to{opacity:1;transform:translateY(0) scale(1);backdrop-filter:blur(28px) saturate(155%)}}
@media(max-width:900px){.mobile-nav{overflow:visible;background:linear-gradient(124deg,rgba(255,255,255,.52),rgba(221,246,247,.38) 54%,rgba(255,255,255,.46));backdrop-filter:blur(24px) saturate(155%) contrast(104%);border:1px solid transparent;box-shadow:0 14px 34px rgba(16,42,67,.18),0 3px 10px rgba(0,106,120,.08),inset 0 1px 0 rgba(255,255,255,.82)}.mobile-nav::before{background:radial-gradient(75% 120% at 7% 0%,rgba(255,255,255,.82),rgba(255,255,255,0) 55%),linear-gradient(115deg,rgba(255,255,255,.3),transparent 52%,rgba(0,140,158,.08));mask:none;padding:0}.mobile-nav::after{content:'';position:absolute;inset:-1px;border-radius:inherit;pointer-events:none;background:linear-gradient(110deg,rgba(255,255,255,.62),rgba(255,255,255,0) 30%,rgba(0,175,193,.15) 72%,rgba(255,255,255,.56));z-index:-2;filter:blur(.2px)}.mobile-nav button{z-index:1;min-width:54px}.mobile-nav button:hover{transform:translateY(-1px)}.mobile-nav button.selected{background:linear-gradient(135deg,rgba(255,255,255,.72),rgba(221,246,247,.58));box-shadow:inset 0 1px 0 rgba(255,255,255,.95),0 5px 14px rgba(0,106,120,.13)}.mobile-nav button.selected::after{box-shadow:0 0 0 3px rgba(0,140,158,.14)}.topbar.compact{height:54px;margin-top:7px;border-radius:16px}}
@media(prefers-reduced-motion:reduce){.glass,.primary-button,.filter,.search,.icon-button,.mobile-nav button{transition:none!important}.notifications-popover,.detail-modal{animation:none!important}}
/* Refinamento: fundo calmo; reflexos e gradientes ficam restritos ao material de navegação. */
.main-content{background:#f5fbfc}.welcome-banner{background:#e7f8f9}.notifications-popover.glass{position:fixed}
@media(max-width:900px){.mobile-nav.glass{position:fixed;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));justify-content:initial;align-items:center;isolation:isolate}.mobile-nav.glass::before{z-index:-3}.mobile-nav.glass::after{z-index:-2}.mobile-nav-bubble{position:absolute;z-index:0;left:8px;width:calc((100% - 16px)/4);height:calc(100% - 14px);border-radius:14px;background:rgba(255,255,255,.46);backdrop-filter:blur(11px) saturate(145%);box-shadow:inset 0 1px 0 rgba(255,255,255,.9),0 5px 14px rgba(0,106,120,.12);transition:transform .42s cubic-bezier(.18,.9,.28,1),width .32s cubic-bezier(.18,.9,.28,1),height .32s cubic-bezier(.18,.9,.28,1),border-radius .32s cubic-bezier(.18,.9,.28,1),background .32s ease,box-shadow .32s ease}.mobile-nav-bubble::before{content:'';position:absolute;inset:1px;border-radius:inherit;background:radial-gradient(80% 95% at 22% 0%,rgba(255,255,255,.72),rgba(255,255,255,0) 62%);pointer-events:none}.mobile-nav button{width:100%;min-width:0;background:transparent!important;box-shadow:none!important}.mobile-nav button.selected{animation:none}.mobile-nav button.selected::after{display:none}.mobile-nav button span{position:relative}.mobile-nav button.selected .svg-icon{filter:invert(42%) sepia(97%) saturate(885%) hue-rotate(142deg) brightness(89%) contrast(101%)}.mobile-nav button:active{transform:scale(.97)} }
/* O modal é a superfície elevada; seu conteúdo não cria novos cards de vidro. */
.modal-backdrop{background:rgba(16,42,67,.24)}.modal.glass{background:rgba(252,254,255,.9);border-color:rgba(255,255,255,.88);box-shadow:0 24px 64px rgba(16,42,67,.2),inset 0 1px 0 rgba(255,255,255,.92);backdrop-filter:blur(34px) saturate(118%)}.detail-list{gap:0;margin-top:20px;border-top:1px solid rgba(100,116,139,.16)}.detail-list div{background:transparent;padding:15px 0;border-radius:0;border-bottom:1px solid rgba(100,116,139,.16)}.detail-list div:nth-child(odd):not(.full){padding-right:16px}.detail-list div:nth-child(even):not(.full){padding-left:16px;border-left:1px solid rgba(100,116,139,.16)}.detail-list .full{padding-bottom:16px}.detail-list dt{color:#64748b}.detail-list dd{color:#102a43}.detail-list em{display:inline-flex}.mobile-nav-bubble{top:7px}
@media(max-width:900px){.detail-list div:nth-child(even):not(.full){padding-left:0;border-left:0}.detail-list div:nth-child(odd):not(.full){padding-right:0}}
@media(prefers-color-scheme:dark){.modal-backdrop{background:rgba(2,19,31,.56)}.modal.glass{background:rgba(12,31,45,.88);border-color:rgba(181,235,239,.18);box-shadow:0 24px 64px rgba(0,0,0,.38),inset 0 1px 0 rgba(225,251,252,.14);backdrop-filter:blur(36px) saturate(110%)}.modal.glass::before{opacity:.38}.detail-list{border-color:rgba(221,246,247,.16)}.detail-list div{border-color:rgba(221,246,247,.14)}.detail-list div:nth-child(even):not(.full){border-left-color:rgba(221,246,247,.14)}.detail-list dt{color:#b7c9d7}.detail-list dd{color:#f6fbfc}}

/* Navegação é vidro contextual: sem preenchimento em gradiente fixo. */
.topbar.glass{background:rgba(249,254,254,.72)}
.topbar.compact{background:rgba(249,254,254,.8)}
@media(max-width:900px){.mobile-nav.glass{background:rgba(248,254,254,.58)}.mobile-nav-bubble{background:rgba(255,255,255,.34)}}
@media(prefers-color-scheme:dark){.topbar.glass{background:rgba(12,31,45,.72)}.topbar.compact{background:rgba(12,31,45,.8)}}
@media(max-width:900px) and (prefers-color-scheme:dark){.mobile-nav.glass{background:rgba(10,33,47,.64)}.mobile-nav-bubble{background:rgba(177,239,243,.12)}}

/* Tema do sistema: conteúdo sólido escurece junto ao material translúcido. */
@media(prefers-color-scheme:dark){
  :root{color-scheme:dark;--bg:#081d2a;--navy:#edf7f8;--neutral:#a8bec9;--line:#214151;--soft:#123b4a}
  body{background:var(--bg);color:var(--navy)}
  .main-content{background:#0b2330}
  .sidebar{background:#071d27;box-shadow:inset -1px 0 rgba(176,249,250,.08)}
  .unit-card{background:#103845}.sidebar-foot{color:#8aadb8}
  .search{background:rgba(11,35,48,.7);border-color:rgba(178,232,236,.14);color:#c1d6dd;box-shadow:inset 0 1px 0 rgba(225,251,252,.08)}
  .stat-card,.panel{background:#102b38;border-color:#1d4352;box-shadow:0 8px 22px rgba(0,0,0,.16)}
  .stat-head,.eyebrow,.page-heading p,.section-intro p,.list-row small,.detail-row small,.notification-item small,.empty-row{color:var(--neutral)}
  .list-row,.detail-row,.filter-row{border-color:var(--line)}
  .interactive-row:hover{background:#143744}
  .welcome-banner{background:#103844}.welcome-banner p{color:#b4cbd3}.banner-logo small{color:var(--navy)}
  .section-icon,.row-icon,.stat-head i{background:#164654;color:#56d8df}
  .place-thumb{background:#164654;color:#56d8df}
  .filter{background:#143845;border-color:#245161;color:#bdd3da}.filter:hover{background:#1a4654}.filter.selected{background:#1b5964;color:#e6ffff}
  .outline-button{border-color:#3b8993;color:#7ee8ed}
  .modal input,.modal select,.modal textarea{background:#0d2734;border-color:#295364;color:#edf7f8}
  .modal p{color:var(--neutral)}
  .red{background:#4a252c;color:#ffabb2}.yellow{background:#493d22;color:#ffd879}.blue{background:#193c5c;color:#93caff}.green{background:#164433;color:#83e5bb}.gray{background:#263e49;color:#c0d0d7}
  .notifications-popover{border-color:rgba(181,235,239,.18)}
}

/* Dark Liquid Glass: material fosco, baixa emissão luminosa e acento reservado à marca. */
@media(prefers-color-scheme:dark){
  :root{--bg:#061d27;--navy:#f1f7f8;--neutral:#a5bcc4;--line:rgba(180,215,225,.12);--soft:#123843}
  .main-content{background:#08232d}
  .glass{
    background:rgba(12,39,50,.68);
    border-color:rgba(180,215,225,.12);
    backdrop-filter:blur(20px) saturate(116%);
    box-shadow:0 12px 32px rgba(0,0,0,.22),inset 0 1px 0 rgba(225,245,250,.16);
  }
  .glass::before{background:radial-gradient(42% 44% at 14% 0%,rgba(225,245,250,.13),transparent 72%);opacity:.8}
  .glass::after{background:linear-gradient(180deg,rgba(225,245,250,.08),transparent 18%);opacity:.55}
  .sidebar.glass{background:rgba(8,35,45,.9);border-color:rgba(180,215,225,.08);box-shadow:inset -1px 0 rgba(225,245,250,.05)}
  .topbar.glass,.topbar.compact{background:rgba(12,39,50,.7);box-shadow:0 12px 32px rgba(0,0,0,.18),inset 0 1px 0 rgba(225,245,250,.16)}
  .topbar:hover{transform:none;box-shadow:0 12px 32px rgba(0,0,0,.22),inset 0 1px 0 rgba(225,245,250,.18)}
  .search{background:rgba(4,24,33,.52);border-color:rgba(180,215,225,.1);box-shadow:inset 0 1px 0 rgba(225,245,250,.06)}
  .search:hover{background:rgba(8,31,41,.72);border-color:rgba(180,215,225,.16);box-shadow:inset 0 1px 0 rgba(225,245,250,.1),0 4px 12px rgba(0,0,0,.12);transform:translateY(-1px)}
  .search:hover{background:rgba(8,31,41,.72);border-color:rgba(180,215,225,.16);box-shadow:inset 0 1px 0 rgba(225,245,250,.1),0 4px 12px rgba(0,0,0,.12);transform:translateY(-1px)}
  .panel,.stat-card{background:rgba(13,45,55,.94);border-color:rgba(180,215,225,.09);box-shadow:0 8px 20px rgba(0,0,0,.12)}
  .stat-card:hover,.panel:hover{box-shadow:0 10px 22px rgba(0,0,0,.16)}
  .notifications-popover{background:rgba(12,39,50,.76);box-shadow:0 16px 36px rgba(0,0,0,.28),inset 0 1px 0 rgba(225,245,250,.18)}
  .primary-button{background:#008c9e;box-shadow:0 6px 14px rgba(0,0,0,.18)}
  .primary-button::before{background:transparent}.primary-button:hover{box-shadow:0 8px 17px rgba(0,0,0,.22);filter:brightness(1.07)}
  .filter,.outline-button{background:rgba(12,39,50,.58);border-color:rgba(180,215,225,.14);box-shadow:inset 0 1px 0 rgba(225,245,250,.08)}
  .filter.selected{background:rgba(31,92,104,.72);box-shadow:inset 0 1px 0 rgba(225,245,250,.14)}
  .interactive-row:hover{background:rgba(20,61,72,.55);box-shadow:none}
  .modal-backdrop{background:rgba(0,10,16,.5);backdrop-filter:blur(8px)}
  .modal.glass{background:rgba(12,39,50,.82);border-color:rgba(190,225,235,.18);backdrop-filter:blur(24px) saturate(116%);box-shadow:0 20px 48px rgba(0,0,0,.36),inset 0 1px 0 rgba(225,245,250,.22)}
  .modal.glass::before{background:radial-gradient(46% 34% at 16% 0%,rgba(225,245,250,.14),transparent 72%);opacity:1}
  .detail-list,.detail-list div{border-color:rgba(180,215,225,.13)}
  .detail-list dd{color:#edf6f7}.detail-list dt{color:#a5bcc4}
  .section-icon,.row-icon,.place-thumb,.stat-head i{background:#123943;color:#35c2ca}
  .mobile-nav.glass{background:rgba(12,39,50,.62);border-color:rgba(190,225,235,.16);backdrop-filter:blur(22px) saturate(116%);box-shadow:0 12px 28px rgba(0,0,0,.26),inset 0 1px 0 rgba(225,245,250,.14)}
  .mobile-nav.glass::before{background:radial-gradient(45% 85% at 14% 0%,rgba(225,245,250,.1),transparent 74%)}
  .mobile-nav.glass::after{display:none}
  .mobile-nav-bubble{background:rgba(75,125,135,.25);box-shadow:inset 0 1px 0 rgba(225,245,250,.16),0 4px 10px rgba(0,0,0,.16)}
  .mobile-nav-bubble::before{background:radial-gradient(70% 80% at 25% 0%,rgba(225,245,250,.16),transparent 70%)}
}

/* Light Liquid Glass: conteúdo sólido, controles e navegação em vidro branco fosco. */
@media(prefers-color-scheme:light){
  :root{color-scheme:light;--bg:#f7fafa;--navy:#102a43;--neutral:#64748b;--line:#e4ecee;--soft:#ddf6f7}
  body{background:var(--bg);color:var(--navy)}
  .main-content{background:#f7fafa}
  .glass{background:rgba(255,255,255,.72);border-color:rgba(16,42,67,.08);backdrop-filter:blur(18px) saturate(120%);box-shadow:0 4px 16px rgba(16,42,67,.06),inset 0 1px 0 rgba(255,255,255,.75)}
  .glass::before{background:radial-gradient(42% 45% at 12% 0%,rgba(255,255,255,.72),transparent 72%);opacity:.68}
  .glass::after{background:linear-gradient(180deg,rgba(255,255,255,.42),transparent 18%);opacity:.42}
  .sidebar,.sidebar.glass{background:rgba(255,255,255,.78);color:var(--navy);border-color:rgba(16,42,67,.06);box-shadow:inset -1px 0 rgba(16,42,67,.04),0 2px 14px rgba(16,42,67,.03)}
  .sidebar .condo-brand{color:var(--navy)}.sidebar .brand-tag,.sidebar-foot{color:#7b909d}.sidebar .nav-item{color:var(--neutral)}.sidebar .nav-item.selected{background:#008c9e;color:#fff;box-shadow:none}.sidebar .nav-item.selected .svg-icon{filter:brightness(0) invert(1)}
  .unit-card{background:#f1f7f7;color:var(--navy)}.unit-card small{color:var(--neutral)}.unit-photo{background:#ddf6f7;color:#008c9e}
  .topbar.glass,.topbar.compact{background:rgba(255,255,255,.74);border-color:rgba(16,42,67,.07);box-shadow:0 4px 16px rgba(16,42,67,.06),inset 0 1px 0 rgba(255,255,255,.78)}
  .topbar:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(16,42,67,.08),inset 0 1px 0 rgba(255,255,255,.8)}
  .search{background:rgba(247,250,250,.78);border-color:rgba(0,140,158,.1);box-shadow:inset 0 1px 0 rgba(255,255,255,.7)}.search:focus-within{border-color:#008c9e;box-shadow:0 0 0 2px rgba(0,140,158,.12)}
  .panel,.stat-card{background:#fff;border-color:#e4ecee;box-shadow:0 4px 16px rgba(16,42,67,.06)}
  .stat-card:hover,.panel:hover{box-shadow:0 5px 18px rgba(16,42,67,.075)}
  .welcome-banner{background:#f1f7f7;border:1px solid rgba(16,42,67,.05)}
  .section-icon,.row-icon,.place-thumb,.stat-head i{background:#ddf6f7;color:#008c9e}
  .filter,.outline-button{background:rgba(255,255,255,.68);border-color:rgba(16,42,67,.1);box-shadow:inset 0 1px 0 rgba(255,255,255,.75)}
  .filter.selected{background:rgba(221,246,247,.78);color:#006a78;box-shadow:inset 0 1px 0 rgba(255,255,255,.8)}
  .primary-button{background:#008c9e;box-shadow:0 5px 12px rgba(16,42,67,.12)}.primary-button::before{background:transparent}.primary-button:hover{background:#007c8c;box-shadow:0 6px 14px rgba(16,42,67,.14);filter:none}.primary-button:active{background:#006a78}
  .interactive-row:hover{background:#f7fafa;box-shadow:none}
  .notifications-popover{background:rgba(255,255,255,.78);border-color:rgba(16,42,67,.08);box-shadow:0 12px 28px rgba(16,42,67,.12),inset 0 1px 0 rgba(255,255,255,.78)}
  .modal-backdrop{background:rgba(16,42,67,.12);backdrop-filter:blur(7px)}
  .modal.glass{background:rgba(255,255,255,.84);border-color:rgba(16,42,67,.08);border-top-color:rgba(255,255,255,.8);backdrop-filter:blur(24px) saturate(120%);box-shadow:0 16px 40px rgba(16,42,67,.16),inset 0 1px 0 rgba(255,255,255,.86)}
  .modal.glass::before{background:radial-gradient(45% 35% at 15% 0%,rgba(255,255,255,.7),transparent 74%);opacity:1}
  .modal input,.modal select,.modal textarea{background:#fff;border-color:#e4ecee;color:#102a43}
  .mobile-nav.glass{background:rgba(255,255,255,.74);border-color:rgba(16,42,67,.08);backdrop-filter:blur(20px) saturate(120%);box-shadow:0 8px 22px rgba(16,42,67,.1),inset 0 1px 0 rgba(255,255,255,.8)}
  .mobile-nav.glass::before{background:radial-gradient(48% 85% at 13% 0%,rgba(255,255,255,.72),transparent 75%)}.mobile-nav.glass::after{display:none}
  .mobile-nav-bubble{background:rgba(221,246,247,.78);box-shadow:inset 0 1px 0 rgba(255,255,255,.86),0 3px 8px rgba(16,42,67,.06)}
  .mobile-nav-bubble::before{background:radial-gradient(70% 80% at 25% 0%,rgba(255,255,255,.58),transparent 72%)}
}

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
  .modal{animation:material-rise .28s cubic-bezier(.2,.8,.2,1)}
}
@keyframes desktop-nav-select{from{transform:translateX(-3px) scale(.985);filter:saturate(.9)}to{transform:translateX(0) scale(1);filter:saturate(1)}}
@keyframes material-rise{from{opacity:0;transform:translateY(8px) scale(.975);filter:blur(2px)}to{opacity:1;transform:translateY(0) scale(1);filter:blur(0)}}
@media(prefers-reduced-motion:reduce){.nav-item,.nav-item .svg-icon,.unit-card{transition:none!important}.nav-item.selected,.modal{animation:none!important}}
.reservation-review-actions{margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}.reservation-review-actions button{min-height:42px;justify-content:center}.reservation-review-actions .svg-icon{width:16px;height:16px}.danger-action{display:inline-flex;align-items:center;gap:7px;border-color:#efb5b9!important;background:#fff5f5!important;color:#b4232c!important}.danger-action:hover{border-color:#dc2626!important;background:#fee8e9!important}.danger-action .svg-icon{filter:invert(22%) sepia(79%) saturate(3299%) hue-rotate(347deg) brightness(89%) contrast(88%)}.approve-action{background:#008c9e}.approve-action:hover{background:#006a78}.danger-action:focus-visible,.approve-action:focus-visible{outline:3px solid rgba(0,175,193,.35);outline-offset:2px}@media(prefers-color-scheme:dark){.danger-action{border-color:#8c3d45!important;background:#3e242a!important;color:#ffb6bc!important}.danger-action:hover{background:#512a31!important}.reservation-review-actions{border-color:rgba(180,215,225,.14)}}
</style>
