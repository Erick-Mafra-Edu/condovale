import { AppError } from '~/domain/app-error'
import type { CommonArea, Reservation, ReservationOccupancy, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'
import { createApiRequest } from './request'

export function createApiReservationRepository(): ReservationRepository {
  const request = createApiRequest()
  const api: typeof request = async (path, options) => {
    try { return await request(path, options) }
    catch (cause) {
      if (cause instanceof AppError && cause.code === 'CONFLICT') throw new AppError('RESERVATION_CONFLICT', cause.message, cause.fields)
      throw cause
    }
  }

  return {
    list: () => api<Reservation[]>('/reservations'),
    listMine: () => api<Reservation[]>('/reservations/me'),
    findById: id => api<Reservation>(`/reservations/${id}`),
    create: input => api<Reservation>('/reservations', { method: 'POST', body: input }),
    cancel: id => api<Reservation>(`/reservations/${id}/cancel`, { method: 'POST' }),
    updateStatus: (id, status: ReservationStatus, userId: string, rejectionReason?: string) => api<Reservation>(`/reservations/${id}/status`, { method: 'PATCH', body: { status, userId, rejectionReason } }),
    listAreas: () => api<CommonArea[]>('/common-areas'),
    listOccupancy: (areaId, startDate, endDate) => api<ReservationOccupancy[]>(`/common-areas/${areaId}/occupancy`, { query: { startDate, endDate } }),
  }
}
