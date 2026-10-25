import { AppError, toAppError } from '~/domain/app-error'
import type { CreateOccurrenceInput, FinishOccurrenceInput, Occurrence, OccurrenceAttendanceInput, OccurrenceAttachment, OccurrenceEmployee, OccurrenceHistory, OccurrenceResident, OccurrenceStatus } from '~/domain/occurrence'
import { useServices } from '~/services'

export function useOccurrences() {
  const { occurrenceService } = useServices()
  const occurrences = ref<Occurrence[]>([])
  const history = ref<OccurrenceHistory[]>([])
  const assignableEmployees = ref<OccurrenceEmployee[]>([])
  const openableResidents = ref<OccurrenceResident[]>([])
  const attachments = ref<OccurrenceAttachment[]>([])
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

  async function assignOccurrence(id: string, employeeId: string, message?: string) {
    return store((await execute(() => occurrenceService.assign(id, employeeId, message))).data)
  }

  async function updateOccurrenceStatus(id: string, status: OccurrenceStatus) {
    return store((await execute(() => occurrenceService.updateStatus(id, status))).data)
  }

  async function updateOccurrenceAttendance(id: string, input: OccurrenceAttendanceInput) {
    return store((await execute(() => occurrenceService.updateAttendance(id, input))).data)
  }

  async function finishOccurrence(id: string, input: FinishOccurrenceInput | string) {
    return store((await execute(() => occurrenceService.finish(id, input))).data)
  }

  async function loadHistory(id: string) {
    const result = await execute(() => occurrenceService.history(id))
    history.value = result.data
    return result.data
  }

  async function loadAssignableEmployees(query?: { search?: string }) {
    const result = await execute(() => occurrenceService.assignableEmployees(query))
    assignableEmployees.value = result.data
    return result.data
  }

  async function loadOpenableResidents(query?: { search?: string }) {
    const result = await execute(() => occurrenceService.openableResidents(query))
    openableResidents.value = result.data
    return result.data
  }

  async function loadAttachments(id: string) {
    const result = await execute(() => occurrenceService.listAttachments(id))
    attachments.value = result.data
    return result.data
  }

  async function uploadAttachment(id: string, file: File) {
    const result = await execute(() => occurrenceService.uploadAttachment(id, file))
    attachments.value.push(result.data)
    return result.data
  }

  async function removeAttachment(id: string, attachmentId: string) {
    await execute(() => occurrenceService.deleteAttachment(id, attachmentId))
    attachments.value = attachments.value.filter(item => item.id !== attachmentId)
  }

  function attachmentUrl(id: string, attachmentId: string) {
    return occurrenceService.attachmentUrl(id, attachmentId)
  }

  return { occurrences, history, assignableEmployees, openableResidents, attachments, loading, error, loadOccurrences, findOccurrence, createOccurrence, assignOccurrence, updateOccurrenceAttendance, updateOccurrenceStatus, finishOccurrence, loadHistory, loadAssignableEmployees, loadOpenableResidents, loadAttachments, uploadAttachment, removeAttachment, attachmentUrl }
}
