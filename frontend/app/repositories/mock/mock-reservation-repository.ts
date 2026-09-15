import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { CommonArea, CreateReservationInput, Reservation, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'
import { mockAreas, mockReservations } from './state'
import { simulateRequest } from './mock-config'

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensureReservation(id: string): Reservation {
  const reservation = mockReservations.find(item => item.id === id)
  if (!reservation) throw new AppError('NOT_FOUND', 'Reserva não encontrada')
  return reservation
}

function ensureNoConflict(input: CreateReservationInput, excludeId?: string) {
  if (mockReservations.some(item => item.id !== excludeId && item.areaId === input.areaId && item.date === input.date && (item.status === 'pending' || item.status === 'approved') && item.startTime < input.endTime && item.endTime > input.startTime)) {
    throw new AppError('RESERVATION_CONFLICT', 'Já existe uma reserva neste horário')
  }
}

export const mockReservationRepository: ReservationRepository = {
  async list() {
    await simulateRequest()
    return response(mockReservations)
  },
  async findById(id) {
    await simulateRequest()
    return response(ensureReservation(id))
  },
  async create(input: CreateReservationInput) {
    await simulateRequest()
    ensureNoConflict(input)
    const reservation: Reservation = {
      ...input, id: crypto.randomUUID(), status: 'pending', createdAt: new Date().toISOString(),
    }
    mockReservations.push(reservation)
    return response(reservation)
  },
  async cancel(id) {
    await simulateRequest()
    const reservation = ensureReservation(id)
    reservation.status = 'cancelled'
    return response(reservation)
  },
  async updateStatus(id, status: ReservationStatus, rejectionReason?: string) {
    await simulateRequest()
    const reservation = ensureReservation(id)
    if (status === 'pending' || status === 'approved') ensureNoConflict(reservation, id)
    reservation.status = status
    reservation.rejectionReason = rejectionReason
    return response(reservation)
  },
  async listAreas(): Promise<ApiResponse<CommonArea[]>> {
    await simulateRequest()
    return response(mockAreas)
  },
  async getBlockingReservations(areaId, date) {
    await simulateRequest()
    return response(mockReservations.filter(item => item.areaId === areaId && item.date === date && item.status !== 'cancelled' && item.status !== 'rejected'))
  },
}
