import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { CommonArea, CreateReservationInput, Reservation, ReservationStatus } from '~/domain/reservation'
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
  async list() {
    await simulateRequest()
    return response(mockReservations)
  },
  async listMine() {
    await simulateRequest()
    const residentId = authenticatedResidentId()
    return response(mockReservations.filter(item => item.residentId === residentId))
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
    if (reservation.residentId !== authenticatedResidentId()) throw new AppError('FORBIDDEN', 'A reserva pertence a outro morador')
    reservation.status = 'cancelled'
    return response(reservation)
  },
  async updateStatus(id, status: ReservationStatus, userId: string, rejectionReason?: string) {
    await simulateRequest()
    const reservation = ensureReservation(id)
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
}
