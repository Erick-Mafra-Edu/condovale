import { AppError } from '~/domain/app-error'
import type { CommonArea, Reservation, ReservationOccupancy, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'
import { createApiRequest } from './request'

export function fromApiArea(value: CommonArea & { image_url?: string; opening_time?: string; closing_time?: string; max_reservation_minutes?: number; requires_approval?: boolean }) {
  const { image_url, opening_time, closing_time, max_reservation_minutes, requires_approval, ...area } = value
  return { ...area, imageUrl: image_url ?? value.imageUrl, openingTime: opening_time ?? value.openingTime, closingTime: closing_time ?? value.closingTime, maxReservationMinutes: max_reservation_minutes ?? value.maxReservationMinutes, requiresApproval: requires_approval ?? value.requiresApproval }
}

export function fromApiReservation(value: Reservation & { common_area_id?: string; resident_id?: string; start_time?: string; end_time?: string; created_at?: string; rejection_reason?: string }) {
  const { common_area_id, resident_id, start_time, end_time, created_at, rejection_reason, ...reservation } = value
  return { ...reservation, areaId: common_area_id ?? value.areaId, residentId: resident_id ?? value.residentId, startTime: start_time ?? value.startTime, endTime: end_time ?? value.endTime, createdAt: created_at ?? value.createdAt, rejectionReason: rejection_reason ?? value.rejectionReason }
}

export function fromApiOccupancy(value: ReservationOccupancy & { start_time?: string; end_time?: string }) {
  return { date: value.date, startTime: value.start_time ?? value.startTime, endTime: value.end_time ?? value.endTime }
}

function mapResponse<T>(response: { data: T; message: string | null }, mapper: (value: any) => any) {
  return { ...response, data: Array.isArray(response.data) ? response.data.map(mapper) : mapper(response.data) }
}

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
    list: async () => mapResponse(await api<Reservation[]>('/reservations'), fromApiReservation),
    listMine: async () => mapResponse(await api<Reservation[]>('/reservations/me'), fromApiReservation),
    findById: async id => mapResponse(await api<Reservation>(`/reservations/${id}`), fromApiReservation),
    create: async input => mapResponse(await api<Reservation>('/reservations', { method: 'POST', body: { common_area_id: input.areaId, date: input.date, start_time: input.startTime, end_time: input.endTime } }), fromApiReservation),
    cancel: async id => mapResponse(await api<Reservation>(`/reservations/${id}/cancel`, { method: 'POST' }), fromApiReservation),
    updateStatus: async (id, status: ReservationStatus, rejectionReason?: string) => mapResponse(await api<Reservation>(`/reservations/${id}/status`, { method: 'PATCH', body: { status, rejection_reason: rejectionReason } }), fromApiReservation),
    listAreas: async () => mapResponse(await api<CommonArea[]>('/common-areas'), fromApiArea),
    listOccupancy: async (areaId, startDate, endDate) => mapResponse(await api<ReservationOccupancy[]>(`/common-areas/${areaId}/occupancy`, { query: { start_date: startDate, end_date: endDate } }), fromApiOccupancy),
  }
}
