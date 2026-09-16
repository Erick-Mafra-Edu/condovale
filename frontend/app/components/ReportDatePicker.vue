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
</script>

<template>
  <label class="report-date-field">{{ label }}<button type="button" class="report-date-input" :aria-expanded="open" :aria-label="`${label}: ${displayValue}`" @click="open = true"><span>{{ displayValue }}</span><SvgIcon name="calendar" /></button></label>
  <div v-if="open" class="date-modal-backdrop" role="presentation" @click.self="open = false">
    <section class="date-modal glass" role="dialog" aria-modal="true" :aria-label="`Selecionar ${label.toLowerCase()}`">
      <div class="date-modal-head"><div><span class="eyebrow">Filtro do relatório</span><h3>Selecionar data</h3></div><button type="button" class="date-modal-close" aria-label="Fechar calendário" @click="open = false">×</button></div>
      <UCalendar v-model="calendarValue" color="primary" variant="subtle" :year-controls="true" />
      <button type="button" class="outline-button date-modal-cancel" @click="open = false">Cancelar</button>
    </section>
  </div>
</template>

<style scoped>
.report-date-field{display:grid;gap:6px;font-size:12px;font-weight:700;color:#36566d}.report-date-input{display:flex;align-items:center;justify-content:space-between;gap:12px;min-width:170px;padding:10px 12px;border:1px solid #d8e6e8;border-radius:10px;background:#fff;color:#102a43;font:inherit;font-weight:500;text-align:left;cursor:pointer}.report-date-input:hover{border-color:#80cbd0;background:#fbfefe}.report-date-input:focus-visible,.date-modal-close:focus-visible,.date-modal-cancel:focus-visible{outline:3px solid rgba(0,140,158,.25);outline-offset:2px}.report-date-input .svg-icon{width:16px;height:16px;opacity:.8}.date-modal-backdrop{position:fixed;inset:0;z-index:20;display:grid;place-items:center;padding:20px;background:rgba(16,42,67,.24);backdrop-filter:blur(5px)}.date-modal{width:min(100%,390px);padding:24px;border:1px solid rgba(16,42,67,.1);border-radius:22px;background:rgba(255,255,255,.94);box-shadow:0 24px 64px rgba(16,42,67,.2)}.date-modal-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px}.date-modal-head h3{margin:8px 0 0;font-size:20px;letter-spacing:-.03em}.date-modal-close{width:30px;height:30px;border:0;border-radius:9px;background:#f1f7f7;color:#36566d;font-size:20px;cursor:pointer}.date-modal-cancel{width:100%;margin-top:18px;justify-content:center}@media(max-width:800px){.report-date-input{min-width:0;width:100%}}@media(prefers-color-scheme:dark){.report-date-field{color:#b9d1d8}.report-date-input{background:#0a2935;border-color:#285263;color:#f1f7f8}.report-date-input:hover{background:#103845}.date-modal-backdrop{background:rgba(0,10,16,.5)}.date-modal{background:rgba(12,39,50,.96);border-color:rgba(180,215,225,.15);box-shadow:0 24px 64px rgba(0,0,0,.36)}.date-modal-head h3{color:#f1f7f8}.date-modal-close{background:#164654;color:#d4e5e8}}
</style>
