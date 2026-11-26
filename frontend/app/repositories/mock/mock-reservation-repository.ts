import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { AreaAvailability, AvailabilityDay, CommonArea, CreateReservationInput, Reservation, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'
import { mockAreas, mockReservations } from './state'
import { simulateRequest } from './mock-config'
import { mockUsers } from './mock-user-repository'
import { recordAudit } from './mock-audit-repository'
import { getMockAuthenticatedUserId } from './mock-session'

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensureReservation(id: string): Reservation {
  const reservation = mockReservations.find(item => item.id === id)
  if (!reservation) throw new AppError('NOT_FOUND', 'Reserva não encontrada')
  return reservation
}

function authenticatedResidentId() {
  const userId = getMockAuthenticatedUserId()
  const user = mockUsers.find(item => item.id === userId && item.status === 'active')
  if (user?.role !== 'resident') throw new AppError('FORBIDDEN', 'Somente moradores ativos podem realizar esta operação')
  return user.id
}

function ensureNoConflict(input: CreateReservationInput, excludeId?: string) {
  const inputStart = input.startTime ?? '00:00'
  const inputEnd = input.endTime ?? '23:59'
  if (mockReservations.some(item => item.id !== excludeId && item.areaId === input.areaId && item.date === input.date && (item.status === 'pending' || item.status === 'approved') && (item.startTime ?? '00:00') < inputEnd && (item.endTime ?? '23:59') > inputStart)) {
    throw new AppError('RESERVATION_CONFLICT', 'Já existe uma reserva neste horário')
  }
}

export const mockReservationRepository: ReservationRepository = {
  async list(page = 1, filters) {
    await simulateRequest()
    const status = filters?.match(/status:=:([a-z]+)/)?.[1]
    const filtered = status ? mockReservations.filter(item => item.status === status) : mockReservations
    return { ...response(filtered.slice((page - 1) * 20, page * 20)), total: filtered.length }
  },
  async listMine(page = 1, filters) {
    await simulateRequest()
    const residentId = authenticatedResidentId()
    const status = filters?.match(/status:=:([a-z]+)/)?.[1]
    const filtered = mockReservations.filter(item => item.residentId === residentId && (!status || item.status === status))
    return { ...response(filtered.slice((page - 1) * 20, page * 20)), total: filtered.length }
  },
  async findById(id) {
    await simulateRequest()
    return response(ensureReservation(id))
  },
  async create(input: CreateReservationInput) {
    await simulateRequest()
    const residentId = authenticatedResidentId()
    const area = mockAreas.find(item => item.id === input.areaId)
    if (!area || area.status !== 'available') throw new AppError('VALIDATION_ERROR', 'Área comum indisponível')
    ensureNoConflict(input)
    const reservation: Reservation = {
      ...input, residentId, id: crypto.randomUUID(), status: area.requiresApproval ? 'pending' : 'approved', createdAt: new Date().toISOString(),
    }
    mockReservations.push(reservation)
    return response(reservation)
  },
  async cancel(id) {
    await simulateRequest()
    const reservation = ensureReservation(id)
    const actor = mockUsers.find(item => item.id === getMockAuthenticatedUserId())
    if (reservation.residentId !== actor?.id && actor?.role !== 'admin') throw new AppError('FORBIDDEN', 'A reserva pertence a outro morador')
    reservation.status = 'cancelled'
    return response(reservation)
  },
  async updateStatus(id, status: ReservationStatus, rejectionReason?: string) {
    await simulateRequest()
    const reservation = ensureReservation(id)
    const userId = getMockAuthenticatedUserId()
    if (!userId) throw new AppError('FORBIDDEN', 'Sessão não encontrada')
    const user = mockUsers.find(item => item.id === userId && item.status === 'active')
    if (user?.role !== 'admin') throw new AppError('FORBIDDEN', 'Somente a administração pode analisar reservas')
    if (status === 'pending' || status === 'approved') ensureNoConflict(reservation, id)
    reservation.status = status
    reservation.rejectionReason = rejectionReason
    recordAudit({ userId, action: `reservation.${status}`, entity: 'reservation', entityId: id, metadata: rejectionReason ? { reason: rejectionReason } : undefined })
    return response(reservation)
  },
  async listAreas(): Promise<ApiResponse<CommonArea[]>> {
    await simulateRequest()
    return response(mockAreas)
  },
  async listOccupancy(areaId, startDate, endDate) {
    await simulateRequest()
    return response(mockReservations
      .filter(item => item.areaId === areaId && item.date >= startDate && item.date <= endDate && (item.status === 'pending' || item.status === 'approved'))
      .map(({ date, startTime, endTime }) => ({ date, startTime, endTime })))
  },
  async availability(areaId, startDate, endDate) {
    await simulateRequest()
    const area = mockAreas.find(item => item.id === areaId)
    if (!area) throw new AppError('NOT_FOUND', 'Área comum não encontrada')
    const toMinutes = (value: string) => { const [h = 0, m = 0] = value.split(':').map(Number); return h * 60 + m }
    const time = (minutes: number) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`
    const days: AvailabilityDay[] = []
    for (let cursor = new Date(`${startDate}T12:00:00`); cursor <= new Date(`${endDate}T12:00:00`); cursor.setDate(cursor.getDate() + 1)) {
      const date = `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`
      const taken = mockReservations.filter(item => item.areaId === areaId && item.date === date && ['pending', 'approved'].includes(item.status))
      const unavailable = area.status !== 'available' || date < new Date().toLocaleDateString('en-CA')
      const slots: AvailabilityDay['slots'] = []
      if (!unavailable && area.openingTime && area.closingTime) for (let from = toMinutes(area.openingTime); from < toMinutes(area.closingTime); from += 30) {
        const end = Math.min(from + 30, toMinutes(area.closingTime))
        slots.push({ startTime: time(from), endTime: time(end), status: taken.some(item => toMinutes(item.startTime ?? '00:00') < end && toMinutes(item.endTime ?? '24:00') > from) ? 'occupied' : 'available' })
      }
      const free = slots.filter(item => item.status === 'available')
      days.push({ date, status: unavailable || !slots.length ? 'unavailable' : free.length === slots.length ? 'available' : free.length ? 'partial' : 'occupied', reason: unavailable ? 'area_unavailable' : !slots.length ? 'no_opening_hours' : null, reasonLabel: unavailable ? 'Área indisponível.' : !slots.length ? 'Horários não cadastrados.' : null, fullDayAvailable: !unavailable && slots.length > 0 && taken.length === 0, slots, occupiedPeriods: taken.map(item => ({ startTime: item.startTime ?? null, endTime: item.endTime ?? null })), freePeriods: free.map(item => ({ startTime: item.startTime, endTime: item.endTime, minutes: toMinutes(item.endTime) - toMinutes(item.startTime) })) })
    }
    return response<AreaAvailability>({ area, startDate, endDate, slotMinutes: 30, days })
  },
  async createArea(input) { await simulateRequest(); const area = { ...input, id: crypto.randomUUID() }; mockAreas.push(area); return response(area) },
  async updateArea(id, input) { await simulateRequest(); const area = mockAreas.find(item => item.id === id); if (!area) throw new AppError('NOT_FOUND', 'Área comum não encontrada'); Object.assign(area, input); return response(area) },
  async removeArea(id) { await simulateRequest(); const index = mockAreas.findIndex(item => item.id === id); if (index < 0) throw new AppError('NOT_FOUND', 'Área comum não encontrada'); if (mockReservations.some(item => item.areaId === id)) throw new AppError('CONFLICT', 'Área com reservas existentes.'); mockAreas.splice(index, 1); return response(null) },
}
