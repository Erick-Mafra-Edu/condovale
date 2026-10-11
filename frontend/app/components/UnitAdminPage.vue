<script setup lang="ts">
import type { CreateUnitInput, Unit, UpdateUnitInput, UserStatus } from '~/domain/user'

type ManagedUnit = Unit

const props = defineProps<{
  units: ManagedUnit[]
  loading?: boolean
  onCreate: (input: CreateUnitInput) => Promise<unknown>
  onUpdate: (id: string, input: UpdateUnitInput) => Promise<unknown>
  onDeactivate: (id: string) => Promise<unknown>
}>()

const search = ref('')
const statusFilter = ref<UserStatus | 'all'>('active')
const editing = ref<ManagedUnit | null>(null)
const formOpen = ref(false)
const formError = ref('')
const requestError = ref('')
const saving = ref(false)
const form = reactive<{ code: string; number: string; block: string; status: UserStatus }>({ code: '', number: '', block: '', status: 'active' })

const filteredUnits = computed(() => props.units.filter(unit => {
  const query = search.value.trim().toLocaleLowerCase()
  const matchesSearch = !query || `${unit.code} ${unit.number} ${unit.block ?? ''}`.toLocaleLowerCase().includes(query)
  return (statusFilter.value === 'all' || unit.status === statusFilter.value) && matchesSearch
}))

function openCreate() {
  editing.value = null
  Object.assign(form, { code: '', number: '', block: '', status: 'active' })
  formError.value = ''
  requestError.value = ''
  formOpen.value = true
}

function openEdit(unit: ManagedUnit) {
  editing.value = unit
  Object.assign(form, { code: unit.code, number: unit.number, block: unit.block ?? '', status: unit.status })
  formError.value = ''
  requestError.value = ''
  formOpen.value = true
}

async function submit() {
  if (!form.code.trim() || !form.number.trim()) {
    formError.value = 'Informe o código e o número da unidade.'
    return
  }

  saving.value = true
  formError.value = ''
  try {
    const input = { code: form.code.trim(), number: form.number.trim(), block: form.block.trim() || undefined, status: form.status }
    if (editing.value) await props.onUpdate(editing.value.id, input)
    else await props.onCreate(input)
    formOpen.value = false
  } catch (cause) {
    formError.value = cause instanceof Error ? cause.message : 'Não foi possível salvar a unidade.'
  } finally {
    saving.value = false
  }
}

async function requestDeactivate(unit: ManagedUnit) {
  if (unit.status !== 'active' || !confirm(`Inativar a unidade ${unit.number}${unit.block ? `, bloco ${unit.block}` : ''}?`)) return
  requestError.value = ''
  try {
    await props.onDeactivate(unit.id)
  } catch (cause) {
    requestError.value = cause instanceof Error ? cause.message : 'Não foi possível inativar a unidade.'
  }
}
</script>

<template>
  <section class="section-intro">
    <div class="section-icon"><SvgIcon name="building" /></div>
    <div><h2>Unidades</h2><p>Cadastre e mantenha as unidades do condomínio.</p></div>
    <button class="primary-button" type="button" @click="openCreate">Nova unidade</button>
  </section>

  <article class="panel detail-panel users-admin-panel">
    <div class="users-toolbar">
      <input v-model="search" class="users-search" type="search" placeholder="Buscar por código, número ou bloco" aria-label="Buscar unidades" />
      <div class="filter-row users-filters" aria-label="Filtrar unidades por status">
        <button v-for="filter in [{ value: 'active', label: 'Ativas' }, { value: 'inactive', label: 'Inativas' }, { value: 'all', label: 'Todas' }]" :key="filter.value" class="filter" :class="{ selected: statusFilter === filter.value }" type="button" @click="statusFilter = filter.value as UserStatus | 'all'">{{ filter.label }}</button>
      </div>
    </div>
    <p v-if="requestError" class="form-error" role="alert">{{ requestError }}</p>
    <div v-if="loading" class="empty-row" role="status">Carregando unidades...</div>
    <div v-else-if="!filteredUnits.length" class="empty-row">Nenhuma unidade encontrada.</div>
    <div v-for="unit in filteredUnits" v-else :key="unit.id" class="detail-row user-row unit-row">
      <span class="avatar user-avatar" aria-hidden="true"><SvgIcon name="building" /></span>
      <span><strong>{{ unit.number }}<span v-if="unit.block"> · Bloco {{ unit.block }}</span></strong><small>Código: {{ unit.code }}</small></span>
      <em :class="unit.status === 'active' ? 'green' : 'gray'">{{ unit.status === 'active' ? 'Ativa' : 'Inativa' }}</em>
      <button class="outline-button" type="button" @click="openEdit(unit)">{{ unit.status === 'inactive' ? 'Reativar' : 'Editar' }}</button>
      <button v-if="unit.status === 'active'" class="outline-button danger-action" type="button" @click="requestDeactivate(unit)">Inativar</button>
    </div>
  </article>

  <div v-if="formOpen" class="modal-backdrop" @click.self="formOpen = false">
    <form class="modal glass" aria-labelledby="unit-form-title" @submit.prevent="submit">
      <button class="modal-close" type="button" aria-label="Fechar" @click="formOpen = false"></button>
      <div class="section-icon"><SvgIcon name="building" /></div>
      <h2 id="unit-form-title">{{ editing ? 'Editar unidade' : 'Nova unidade' }}</h2>
      <p>Informe os dados cadastrais da unidade.</p>
      <label>Código<input v-model="form.code" autocomplete="off" required /></label>
      <label>Número<input v-model="form.number" autocomplete="off" required /></label>
      <label>Bloco (opcional)<input v-model="form.block" autocomplete="off" /></label>
      <label v-if="editing">Status<select v-model="form.status"><option value="active">Ativa</option><option value="inactive">Inativa</option></select></label>
      <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>
      <div class="modal-actions"><button class="outline-button" type="button" @click="formOpen = false">Cancelar</button><button class="primary-button" type="submit" :disabled="saving">{{ saving ? 'Salvando...' : 'Salvar unidade' }}</button></div>
    </form>
  </div>
</template>
