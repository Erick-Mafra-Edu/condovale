import { AppError, toAppError } from '~/domain/app-error'
import type { CreateReservationInput, Reservation, ReservationStatus } from '~/domain/reservation'
import { useServices } from '~/services'

export function useReservations() {
  const { reservationService } = useServices()
  const reservations = ref<Reservation[]>([])
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

  async function loadReservations() {
    const result = await execute(() => reservationService.list())
    reservations.value = result.data
  }

  async function createReservation(input: CreateReservationInput) {
    const result = await execute(() => reservationService.reserve(input))
    reservations.value.push(result.data)
    return result.data
  }

  async function cancelReservation(id: string) {
    const result = await execute(() => reservationService.cancel(id))
    const index = reservations.value.findIndex(item => item.id === id)
    if (index >= 0) reservations.value[index] = result.data
  }

  async function loadMyReservations(residentId: string) {
    const result = await execute(() => reservationService.getMyReservations(residentId))
    reservations.value = result.data
  }

  async function updateReservationStatus(id: string, status: ReservationStatus, reason?: string) {
    const result = await execute(() => reservationService.updateStatus(id, status, reason))
    const index = reservations.value.findIndex(item => item.id === id)
    if (index >= 0) reservations.value[index] = result.data
    return result.data
  }

  const listAreas = () => execute(() => reservationService.listAreas())
  const findReservation = (id: string) => execute(() => reservationService.findById(id))
  const getBlockingReservations = (areaId: string, date: string) => execute(() => reservationService.getBlockingReservations(areaId, date))

  return { reservations, loading, error, loadReservations, loadMyReservations, createReservation, cancelReservation, updateReservationStatus, listAreas, findReservation, getBlockingReservations }
}
