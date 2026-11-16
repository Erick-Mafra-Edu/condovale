<script setup lang="ts">
import { parseDate, type DateValue } from '@internationalized/date'

const props = defineProps<{ label: string; modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const open = ref(false)

const calendarValue = computed<DateValue | undefined>({
  get: () => props.modelValue ? parseDate(props.modelValue) : undefined,
  set: value => {
    if (!value) return
    emit('update:modelValue', value.toString())
    open.value = false
  },
})

const displayValue = computed(() => {
  if (!props.modelValue) return 'Selecione uma data'
  return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${props.modelValue}T12:00:00`))
})

function formatCalendarHeading(date: DateValue) {
  return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(new Date(`${date.toString()}T12:00:00`)).replace(/^./, value => value.toUpperCase()).replace(' de ', ' ')
}
</script>

<template>
  <div class="report-date-field">{{ label }}<button type="button" class="report-date-input" :aria-expanded="open" :aria-label="`${label}: ${displayValue}`" @click="open = true"><span>{{ displayValue }}</span><SvgIcon name="calendar" /></button></div>
  <div v-if="open" class="date-modal-backdrop" role="presentation" @click.self="open = false">
    <section class="date-modal glass" role="dialog" aria-modal="true" :aria-label="`Selecionar ${label.toLowerCase()}`">
      <div class="date-modal-head"><div><span class="eyebrow">Filtro do relatório</span><h3>Selecionar data</h3></div><button type="button" class="date-modal-close" aria-label="Fechar calendário" @click="open = false">×</button></div>
      <UCalendar v-model="calendarValue" locale="pt-BR" color="primary" variant="subtle" size="lg" :month-controls="true" :year-controls="false" :view-control="false">
        <template #heading="{ date }"><span class="calendar-heading">{{ formatCalendarHeading(date) }}</span></template>
      </UCalendar>
      <p class="date-modal-hint">A data é aplicada ao selecionar um dia.</p>
      <div class="date-modal-actions"><button type="button" class="outline-button date-modal-cancel" @click="open = false">Cancelar</button></div>
    </section>
  </div>
</template>

<style scoped>
.report-date-field{display:grid;gap:6px;font-size:12px;font-weight:700;color:var(--navy)}.report-date-input{display:flex;align-items:center;justify-content:space-between;gap:12px;min-width:170px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--input);color:var(--navy);font:inherit;font-weight:500;text-align:left;cursor:pointer}.report-date-input:hover{border-color:var(--primary);background:var(--hover)}.report-date-input:focus-visible,.date-modal-close:focus-visible,.date-modal-cancel:focus-visible{outline:3px solid var(--focus);outline-offset:2px}.report-date-input .svg-icon{width:16px;height:16px;opacity:.8}.date-modal-backdrop{position:fixed;inset:0;z-index:20;display:grid;place-items:center;padding:20px;background:rgba(16,42,67,.24);backdrop-filter:blur(5px)}.date-modal{width:min(100%,390px);padding:24px;border:1px solid var(--glass-border);border-radius:22px;background:var(--glass);box-shadow:0 24px 64px rgba(15,23,42,.18)}.date-modal-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px}.date-modal-head h3{margin:8px 0 0;font-size:20px;letter-spacing:-.03em}.date-modal-close{width:40px;height:40px;border:1px solid var(--line);border-radius:11px;background:var(--surface-raised);color:var(--navy);font-size:22px;line-height:1;cursor:pointer}.calendar-heading{display:block;width:100%;text-align:center;color:var(--navy);font-size:15px;font-weight:800;text-transform:capitalize}.date-modal-hint{margin:16px 0 0;color:var(--neutral);font-size:12px}.date-modal-actions{display:flex;justify-content:flex-end;margin-top:16px}.date-modal-cancel{min-width:104px;justify-content:center}.date-modal :deep([data-slot="header"]){display:grid;grid-template-columns:40px 1fr 40px;align-items:center;gap:8px;margin-bottom:18px}.date-modal :deep([data-slot="header"] > button){width:40px;height:40px;display:grid;place-items:center;border-radius:10px}.date-modal :deep([data-slot="heading"]){display:flex;align-items:center;justify-content:center;min-height:40px}.date-modal :deep([data-slot="grid"]){width:100%}.date-modal :deep([data-slot="gridWeekDaysRow"]),.date-modal :deep([data-slot="gridRow"]){display:grid;grid-template-columns:repeat(7,minmax(40px,1fr));gap:4px}.date-modal :deep([data-slot="headCell"]){display:grid;place-items:center;height:32px;color:var(--neutral);font-size:11px;font-weight:800;text-transform:uppercase}.date-modal :deep([data-slot="cell"]){display:grid;place-items:center}.date-modal :deep([data-slot="cellTrigger"]){width:40px;height:40px;display:grid;place-items:center;border:1px solid transparent;border-radius:10px;color:var(--navy);font-size:13px;font-weight:600}.date-modal :deep([data-slot="cellTrigger"]:hover){background:var(--hover);color:var(--deep)}.date-modal :deep([data-slot="cellTrigger"]:focus-visible){outline:3px solid var(--focus);outline-offset:2px}.date-modal :deep([data-slot="cellTrigger"][data-today]){border-color:var(--secondary)}.date-modal :deep([data-slot="cellTrigger"][data-selected]),.date-modal :deep([data-slot="cellTrigger"][aria-selected="true"]){background:var(--primary);color:#fff;border-color:var(--primary);font-weight:800}.date-modal :deep([data-slot="cellTrigger"][data-disabled]),.date-modal :deep([data-slot="cellTrigger"][aria-disabled="true"]){opacity:.38;cursor:not-allowed}.date-modal :deep([data-slot="cellTrigger"][data-outside-view]){opacity:.42}.date-modal :deep([data-slot="cellTrigger"][data-selected][data-today]){box-shadow:inset 0 0 0 2px #fff}@media(max-width:800px){.report-date-input{min-width:0;width:100%}.date-modal :deep([data-slot="gridWeekDaysRow"]),.date-modal :deep([data-slot="gridRow"]){grid-template-columns:repeat(7,minmax(34px,1fr));gap:2px}.date-modal :deep([data-slot="cellTrigger"]){width:40px;height:40px}}
</style>
