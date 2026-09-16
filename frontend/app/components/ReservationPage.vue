<script setup lang="ts">
import type { CommonArea, Reservation } from '~/domain/reservation'
import { useReservations } from '~/composables/use-reservations'

const props = defineProps<{
  areas: CommonArea[]
  reservations: Reservation[]
  residentId?: string
}>()

const emit = defineEmits<{ reservationCreated: [] }>()
const { createReservation } = useReservations()

const selectedAreaId = ref(props.areas[0]?.id ?? '')
const visibleMonth = ref(new Date(2026, 8, 1))
const selectedDate = ref('2026-09-24')
const selectedSlot = ref<string | null>(null)
const submitting = ref(false)
const feedback = ref('')

const selectedArea = computed(() => props.areas.find(area => area.id === selectedAreaId.value) ?? props.areas[0])
const monthLabel = computed(() => new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(visibleMonth.value).replace(/^./, value => value.toUpperCase()))
const weekdays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']
const dateKey = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const selectedDateObject = computed(() => new Date(`${selectedDate.value}T12:00:00`))

const reservationForDate = (date: string) => props.reservations.filter(item => item.areaId === selectedArea.value?.id && item.date === date && ['pending', 'approved'].includes(item.status))
const parseMinutes = (value: string) => { const [hour = 0, minute = 0] = value.split(':').map(Number); return hour * 60 + minute }
const slots = computed(() => {
  const area = selectedArea.value
  if (!area?.openingTime || !area.closingTime) return []
  const result: string[] = []
  for (let start = parseMinutes(area.openingTime); start < parseMinutes(area.closingTime); start += 2 * 60) {
    const end = Math.min(start + 2 * 60, parseMinutes(area.closingTime))
    result.push(`${String(Math.floor(start / 60)).padStart(2, '0')}:${String(start % 60).padStart(2, '0')} – ${String(Math.floor(end / 60)).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}`)
  }
  return result
})
const isSlotOccupied = (slot: string, date = selectedDate.value) => {
  const [fromText = '00:00', toText = '00:00'] = slot.split(' – ')
  const from = parseMinutes(fromText); const to = parseMinutes(toText)
  return reservationForDate(date).some(item => parseMinutes(item.startTime) < to && parseMinutes(item.endTime) > from)
}
const dateStatus = (date: string) => {
  const area = selectedArea.value
  if (!area || area.status === 'unavailable' || !slots.value.length) return 'unavailable'
  const occupied = slots.value.filter(slot => isSlotOccupied(slot, date)).length
  if (occupied === 0) return 'available'
  if (occupied >= slots.value.length) return 'occupied'
  return 'partial'
}
const calendarDays = computed(() => {
  const year = visibleMonth.value.getFullYear(); const month = visibleMonth.value.getMonth()
  const firstDay = new Date(year, month, 1); const offset = (firstDay.getDay() + 6) % 7
  const days: Array<{ date: Date; key: string; current: boolean }> = []
  for (let index = 0; index < 42; index++) { const date = new Date(year, month, 1 - offset + index); days.push({ date, key: dateKey(date), current: date.getMonth() === month }) }
  return days
})
const selectedStatus = computed(() => dateStatus(selectedDate.value))
const occupiedSlots = computed(() => slots.value.filter(slot => isSlotOccupied(slot)))
const availableSlots = computed(() => slots.value.filter(slot => !isSlotOccupied(slot)))
const fullDateLabel = computed(() => new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' }).format(selectedDateObject.value).replace(/^./, value => value.toUpperCase()))
const statusLabel = (status: string) => ({ available: 'Disponível', partial: 'Parcialmente ocupado', occupied: 'Ocupado', unavailable: 'Indisponível' }[status] ?? status)

function changeMonth(amount: number) { visibleMonth.value = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth() + amount, 1) }
function selectDate(date: string) { selectedDate.value = date; selectedSlot.value = null; feedback.value = '' }
async function requestReservation() {
  if (!props.residentId || !selectedArea.value || !selectedSlot.value) return
  const [startTime = '', endTime = ''] = selectedSlot.value.split(' – ')
  submitting.value = true; feedback.value = ''
  try {
    await createReservation({ areaId: selectedArea.value.id, residentId: props.residentId, date: selectedDate.value, startTime, endTime })
    feedback.value = 'Solicitação enviada para análise.'
    selectedSlot.value = null
    emit('reservationCreated')
  } catch { feedback.value = 'Não foi possível solicitar este horário.' }
  finally { submitting.value = false }
}
</script>

<template>
  <section class="reservation-page">
    <div class="reservation-heading">
      <div><span class="eyebrow">Agenda do condomínio</span><h2>Reservas</h2><p>Consulte a disponibilidade dos espaços e escolha um horário.</p></div>
      <label class="area-picker"><span>Área comum</span><select v-model="selectedAreaId" @change="selectedSlot = null"><option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }}</option></select></label>
    </div>

    <div class="reservation-layout">
      <article class="reservation-calendar panel">
        <div class="calendar-toolbar"><button class="calendar-nav" aria-label="Mês anterior" @click="changeMonth(-1)">←</button><div><strong>{{ monthLabel }}</strong><span>Selecione um dia para ver os horários</span></div><button class="calendar-nav" aria-label="Próximo mês" @click="changeMonth(1)">→</button></div>
        <div class="weekday-grid" aria-hidden="true"><span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span></div>
        <div class="days-grid" role="grid" aria-label="Calendário de reservas">
          <button v-for="day in calendarDays" :key="day.key" type="button" role="gridcell" :aria-label="`${day.key}, ${statusLabel(dateStatus(day.key))}`" :aria-pressed="selectedDate === day.key" :class="['day-cell', `availability-${dateStatus(day.key)}`, { 'is-outside': !day.current, 'is-selected': selectedDate === day.key, 'is-today': day.key === '2026-09-15' }]" @click="selectDate(day.key)"><strong>{{ day.date.getDate() }}</strong><span class="availability-mark" aria-hidden="true"></span><small>{{ statusLabel(dateStatus(day.key)) }}</small></button>
        </div>
        <div class="availability-legend" aria-label="Legenda de disponibilidade"><span><i class="legend-mark available"></i>Disponível</span><span><i class="legend-mark partial"></i>Parcialmente ocupado</span><span><i class="legend-mark occupied"></i>Ocupado</span></div>
      </article>

      <aside class="reservation-details glass" aria-live="polite">
        <span class="eyebrow">Detalhes do dia</span><h3>{{ fullDateLabel }}</h3><p class="selected-area-name">{{ selectedArea?.name }}</p><div :class="['day-status', `status-${selectedStatus}`]"><span class="status-symbol" aria-hidden="true"></span><strong>{{ statusLabel(selectedStatus) }}</strong></div>
        <div class="time-section"><h4>Horários disponíveis <span>{{ availableSlots.length }}</span></h4><div class="time-list"><button v-for="slot in availableSlots" :key="slot" type="button" :class="['time-slot', { selected: selectedSlot === slot }]" @click="selectedSlot = slot">{{ slot }}<span v-if="selectedSlot === slot" aria-hidden="true">✓</span></button><p v-if="!availableSlots.length" class="muted-copy">Não há horários livres neste dia.</p></div></div>
        <div class="time-section occupied-section"><h4>Horários ocupados <span>{{ occupiedSlots.length }}</span></h4><div class="time-list"><div v-for="slot in occupiedSlots" :key="slot" class="time-slot occupied">{{ slot }}<span>Reservado</span></div><p v-if="!occupiedSlots.length" class="muted-copy">Nenhum horário reservado.</p></div></div>
        <div class="area-rules"><h4>Sobre este espaço</h4><dl><div><dt>Capacidade</dt><dd>{{ selectedArea?.capacity ?? '—' }} pessoas</dd></div><div><dt>Horário permitido</dt><dd>{{ selectedArea?.openingTime }}–{{ selectedArea?.closingTime }}</dd></div><div><dt>Duração máxima</dt><dd>2 horas</dd></div><div><dt>Aprovação</dt><dd>{{ selectedArea?.requiresApproval ? 'Administração' : 'Automática' }}</dd></div></dl></div>
        <p v-if="feedback" class="reservation-feedback" role="status">{{ feedback }}</p><button class="primary-button reservation-submit" :disabled="!selectedSlot || submitting" @click="requestReservation">{{ submitting ? 'Enviando...' : 'Solicitar reserva' }}</button>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.reservation-page{max-width:1180px;margin:0 auto;padding-bottom:40px;color:#102a43}.reservation-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:24px}.reservation-heading h2{margin:8px 0 5px;font-size:30px;letter-spacing:-.04em}.reservation-heading p{margin:0;color:#64748b;font-size:14px}.area-picker{display:grid;gap:7px;min-width:230px;color:#36566d;font-size:12px;font-weight:800}.area-picker select{min-height:44px;border:1px solid #cfe3e5;border-radius:12px;background:#fff;color:#102a43;padding:0 38px 0 14px;font:inherit;cursor:pointer}.reservation-layout{display:grid;grid-template-columns:minmax(0,1.38fr) minmax(320px,.62fr);gap:18px;align-items:start}.reservation-calendar{padding:24px}.calendar-toolbar{display:grid;grid-template-columns:44px 1fr 44px;align-items:center;gap:12px;margin-bottom:26px}.calendar-toolbar>div{text-align:center}.calendar-toolbar strong{display:block;font-size:20px;letter-spacing:-.03em}.calendar-toolbar span{display:block;color:#64748b;font-size:12px;margin-top:5px}.calendar-nav{width:44px;height:44px;border:1px solid #cfe3e5;border-radius:12px;background:#fff;color:#006a78;font-size:24px;line-height:1;cursor:pointer;transition:.2s ease}.calendar-nav:hover{background:#ddf6f7;border-color:#00afc1}.calendar-nav:focus-visible,.day-cell:focus-visible,.time-slot:focus-visible,.area-picker select:focus-visible{outline:3px solid rgba(0,175,193,.4);outline-offset:2px}.weekday-grid,.days-grid{display:grid;grid-template-columns:repeat(7,minmax(44px,1fr));gap:6px}.weekday-grid{margin-bottom:8px}.weekday-grid span{text-align:center;color:#64748b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.day-cell{position:relative;min-height:66px;border:1px solid transparent;border-radius:12px;background:#f8fbfb;color:#102a43;display:grid;place-items:center;align-content:center;gap:3px;cursor:pointer;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease,background .18s ease}.day-cell strong{font-size:15px}.day-cell small{font-size:9px;font-weight:800;line-height:1;opacity:.78}.day-cell:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(16,42,67,.1);border-color:#00afc1}.day-cell.is-selected{border:2px solid #006a78;box-shadow:0 0 0 3px rgba(221,246,247,.95),0 7px 16px rgba(0,106,120,.18);z-index:1}.day-cell.is-today:after{content:'Hoje';position:absolute;top:4px;right:6px;color:#006a78;font-size:8px;font-weight:900}.availability-mark{width:7px;height:7px;border-radius:50%;background:#64748b}.availability-available{background:#effaf3;color:#166534}.availability-available .availability-mark,.legend-mark.available{background:#16a34a}.availability-partial{background:#fff8e8;color:#92400e}.availability-partial .availability-mark,.legend-mark.partial{background:#f59e0b}.availability-occupied{background:#fff1f2;color:#991b1b}.availability-occupied .availability-mark,.legend-mark.occupied{background:#dc2626}.availability-unavailable{background:#f1f5f6;color:#84959b}.day-cell.is-outside{opacity:.38}.availability-legend{display:flex;flex-wrap:wrap;gap:16px;margin-top:24px;padding-top:18px;border-top:1px solid #e4ecee;color:#526d7c;font-size:12px}.availability-legend span{display:inline-flex;align-items:center;gap:7px}.legend-mark{display:inline-block;width:9px;height:9px;border-radius:50%}.reservation-details{padding:24px;border-radius:20px}.reservation-details h3{margin:9px 0 4px;font-size:22px;letter-spacing:-.03em}.selected-area-name{margin:0;color:#64748b;font-size:14px}.day-status{display:flex;align-items:center;gap:9px;margin:20px 0;padding:11px 13px;border-radius:11px;font-size:13px}.status-symbol{width:10px;height:10px;border-radius:50%}.status-available{background:#effaf3;color:#166534}.status-available .status-symbol{background:#16a34a}.status-partial{background:#fff8e8;color:#92400e}.status-partial .status-symbol{background:#f59e0b}.status-occupied{background:#fff1f2;color:#991b1b}.status-occupied .status-symbol{background:#dc2626}.status-unavailable{background:#f1f5f6;color:#526d7c}.status-unavailable .status-symbol{background:#84959b}.time-section{margin-top:20px}.time-section h4,.area-rules h4{display:flex;justify-content:space-between;align-items:center;margin:0 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:.07em}.time-section h4 span{color:#64748b;font-size:11px}.time-list{display:grid;gap:8px}.time-slot{min-height:40px;border:1px solid #b9e2e5;border-radius:10px;background:#f8ffff;color:#006a78;padding:0 12px;text-align:left;font:inherit;font-size:13px;font-weight:800;display:flex;justify-content:space-between;align-items:center;cursor:pointer}.time-slot:hover,.time-slot.selected{background:#ddf6f7;border-color:#008c9e;box-shadow:0 0 0 2px rgba(0,175,193,.16)}.time-slot.selected{background:#008c9e;color:#fff}.time-slot.occupied{border-color:#e6d6d8;background:#faf4f4;color:#8c6267;cursor:not-allowed;font-weight:600}.time-slot.occupied span{font-size:10px;text-transform:uppercase;letter-spacing:.06em}.muted-copy{margin:4px 0;color:#64748b;font-size:12px}.area-rules{margin-top:22px;padding-top:18px;border-top:1px solid rgba(255,255,255,.2)}.area-rules dl{display:grid;gap:9px;margin:0}.area-rules dl div{display:flex;justify-content:space-between;gap:12px;font-size:12px}.area-rules dt{color:#64748b}.area-rules dd{margin:0;text-align:right;color:#102a43;font-weight:800}.reservation-submit{width:100%;justify-content:center;margin-top:22px;min-height:44px}.reservation-feedback{margin:14px 0 0;padding:10px 12px;border-radius:9px;background:#ddf6f7;color:#006a78;font-size:12px;font-weight:700}@media(prefers-color-scheme:dark){.reservation-page{color:#f1f7f8}.reservation-heading p,.calendar-toolbar span,.selected-area-name,.weekday-grid span,.availability-legend,.area-rules dt,.muted-copy{color:#a5bcc4}.area-picker{color:#b9d1d8}.area-picker select,.calendar-nav{background:#0d2d37;border-color:#285263;color:#f1f7f8}.reservation-calendar{background:#0d2d37;border-color:rgba(180,215,225,.12)}.day-cell{background:#123640;color:#f1f7f8}.day-cell.is-today:after{color:#7ce1e6}.availability-available{background:#123d38;color:#a7e7bd}.availability-partial{background:#493a1e;color:#f7ca70}.availability-occupied{background:#492b30;color:#ffadb3}.availability-unavailable{background:#27383d;color:#9bacb1}.availability-legend{border-color:rgba(180,215,225,.14)}.reservation-details{background:rgba(13,45,55,.86);border-color:rgba(180,215,225,.18)}.area-rules{border-color:rgba(180,215,225,.16)}.area-rules dd{color:#e8f5f6}.time-slot{background:#123d43;border-color:#397b81;color:#a9f0f0}.time-slot:hover,.time-slot.selected{background:#008c9e;color:#fff}.time-slot.occupied{background:#392b30;border-color:#68494d;color:#e8aeb3}}
@media(max-width:800px){.reservation-heading{display:block}.area-picker{margin-top:18px;min-width:0}.reservation-layout{grid-template-columns:1fr}.reservation-calendar{padding:16px}.weekday-grid,.days-grid{gap:4px}.day-cell{min-height:54px;border-radius:10px}.day-cell small{font-size:8px}.reservation-details{padding:20px}.calendar-toolbar{margin-bottom:20px}}
@media(max-width:480px){.weekday-grid,.days-grid{grid-template-columns:repeat(7,minmax(36px,1fr))}.day-cell{min-height:49px}.day-cell strong{font-size:14px}.day-cell small{display:none}.day-cell.is-today:after{content:'•';top:1px;right:5px;font-size:14px}.availability-legend{gap:10px;font-size:11px}}
</style>
