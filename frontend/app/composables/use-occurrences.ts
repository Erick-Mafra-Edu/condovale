import { AppError, toAppError } from '~/domain/app-error'
import type { CreateOccurrenceInput, Occurrence, OccurrenceHistory, OccurrenceStatus } from '~/domain/occurrence'
import { useServices } from '~/services'

export function useOccurrences() {
  const { occurrenceService } = useServices()
  const occurrences = ref<Occurrence[]>([])
  const history = ref<OccurrenceHistory[]>([])
  const loading = ref(false)
  const error = ref<AppError | null>(null)

  async function execute<T>(action: () => Promise<T>): Promise<T> {
    loading.value = true
    error.value = null
    try { return await action() }
    catch (cause) {
      const normalized = toAppError(cause)
      error.value = normalized
      throw normalized
    } finally { loading.value = false }
  }

  function store(occurrence: Occurrence) {
    const index = occurrences.value.findIndex(item => item.id === occurrence.id)
    if (index >= 0) occurrences.value[index] = occurrence
    else occurrences.value.push(occurrence)
    history.value = []
    return occurrence
  }

  async function loadOccurrences() {
    const result = await execute(() => occurrenceService.list())
    occurrences.value = result.data
  }

  async function findOccurrence(id: string) {
    const result = await execute(() => occurrenceService.findById(id))
    return result.data
  }

  async function createOccurrence(input: CreateOccurrenceInput) {
    return store((await execute(() => occurrenceService.create(input))).data)
  }

  async function assignOccurrence(id: string, employeeId: string, userId: string) {
    return store((await execute(() => occurrenceService.assign(id, employeeId, userId))).data)
  }

  async function updateOccurrenceStatus(id: string, status: OccurrenceStatus, userId: string) {
    return store((await execute(() => occurrenceService.updateStatus(id, status, userId))).data)
  }

  async function finishOccurrence(id: string, userId: string, message?: string) {
    return store((await execute(() => occurrenceService.finish(id, userId, message))).data)
  }

  async function loadHistory(id: string) {
    const result = await execute(() => occurrenceService.history(id))
    history.value = result.data
    return result.data
  }

  return { occurrences, history, loading, error, loadOccurrences, findOccurrence, createOccurrence, assignOccurrence, updateOccurrenceStatus, finishOccurrence, loadHistory }
}
