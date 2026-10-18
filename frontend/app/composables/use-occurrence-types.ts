import { toAppError, type AppError } from '~/domain/app-error'
import type { CreateOccurrenceTypeInput, OccurrenceType, UpdateOccurrenceTypeInput } from '~/domain/occurrence-type'
import { useServices } from '~/services'

export function useOccurrenceTypes() {
  const { occurrenceTypeService } = useServices()
  const types = ref<OccurrenceType[]>([])
  const activeTypes = ref<OccurrenceType[]>([])
  const loading = ref(false)
  const error = ref<AppError | null>(null)
  async function execute<T>(action: () => Promise<T>): Promise<T> {
    loading.value = true; error.value = null
    try { return await action() } catch (cause) { const failure = toAppError(cause); error.value = failure; throw failure } finally { loading.value = false }
  }
  async function load(query?: { active?: boolean; search?: string }) { types.value = (await execute(() => occurrenceTypeService.list(query))).data; return types.value }
  async function loadActive() { activeTypes.value = (await execute(() => occurrenceTypeService.list({ active: true }))).data; return activeTypes.value }
  async function find(id: string) { return (await execute(() => occurrenceTypeService.findById(id))).data }
  async function create(input: CreateOccurrenceTypeInput) { const value = (await execute(() => occurrenceTypeService.create(input))).data; types.value.push(value); return value }
  async function update(id: string, input: UpdateOccurrenceTypeInput) { const value = (await execute(() => occurrenceTypeService.update(id, input))).data; const index = types.value.findIndex(item => item.id === id); if (index >= 0) types.value[index] = value; return value }
  async function remove(id: string) { await execute(() => occurrenceTypeService.remove(id)); await load() }
  return { types, activeTypes, loading, error, load, loadActive, find, create, update, remove }
}
