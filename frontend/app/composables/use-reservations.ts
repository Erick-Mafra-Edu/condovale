import { AppError, toAppError } from '~/domain/app-error'
import type { CommonAreaInput, CreateReservationInput, Reservation, ReservationStatus } from '~/domain/reservation'
import { useServices } from '~/services'

export function useReservations() {
  const { reservationService } = useServices()
  const reservations = ref<Reservation[]>([])
  const loading = ref(false)
  const error = ref<AppError | null>(null)
  const total = ref(0)

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

  async function loadReservations(page = 1, filters?: string) {
    const result = await execute(() => reservationService.list(page, filters))
    reservations.value = result.data
    total.value = result.total ?? result.data.length
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

  async function loadMyReservations(page = 1, filters?: string) {
    const result = await execute(() => reservationService.getMyReservations(page, filters))
    reservations.value = result.data
    total.value = result.total ?? result.data.length
  }

  async function updateReservationStatus(id: string, status: ReservationStatus, reason?: string) {
    const result = await execute(() => reservationService.updateStatus(id, status, reason))
    const index = reservations.value.findIndex(item => item.id === id)
    if (index >= 0) reservations.value[index] = result.data
    return result.data
  }

  const listAreas = () => execute(() => reservationService.listAreas())
  const findReservation = (id: string) => execute(() => reservationService.findById(id))
  const listOccupancy = (areaId: string, startDate: string, endDate: string) => execute(() => reservationService.listOccupancy(areaId, startDate, endDate))
  const availability = (areaId: string, startDate: string, endDate: string) => execute(() => reservationService.availability(areaId, startDate, endDate))
  const createArea = (input: CommonAreaInput) => execute(() => reservationService.createArea(input))
  const updateArea = (id: string, input: Partial<CommonAreaInput>) => execute(() => reservationService.updateArea(id, input))
  const removeArea = (id: string) => execute(() => reservationService.removeArea(id))

  return { reservations, total, loading, error, loadReservations, loadMyReservations, createReservation, cancelReservation, updateReservationStatus, listAreas, findReservation, listOccupancy, availability, createArea, updateArea, removeArea }
}
