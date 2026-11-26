import { AppError } from '~/domain/app-error'
import { asId, type ApiResponse } from '~/domain/common'
import type { AreaAvailability, AvailabilityDay, AvailabilityPeriod, AvailabilitySlot, CommonArea, CommonAreaInput, Reservation, ReservationOccupancy, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'
import { createApiRequest } from './request'

interface ApiArea { id: number; name: string; description?: string; image_url?: string; capacity?: number; opening_time?: string; closing_time?: string; max_reservation_minutes?: number; requires_approval: boolean; status: CommonArea['status'] }
interface ApiReservation { id: number; common_area_id: number; resident_id: number; date: string; start_time?: string | null; end_time?: string | null; status: ReservationStatus; created_at: string; rejection_reason?: string | null; resident?: { name: string; unit?: { code: string } | null } | null }
interface ApiPeriod { start_time: string | null; end_time: string | null; minutes?: number }
interface ApiSlot { start_time: string; end_time: string; status: AvailabilitySlot['status'] }
interface ApiDay { date: string; status: AvailabilityDay['status']; reason: string | null; reason_label: string | null; full_day_available: boolean; free_periods: ApiPeriod[]; occupied_periods: ApiPeriod[]; slots: ApiSlot[] }
interface ApiAvailability { common_area: ApiArea; start_date: string; end_date: string; slot_minutes: number; days: ApiDay[] }

export function fromApiArea(value: ApiArea): CommonArea { return { id: asId(value.id), name: value.name, description: value.description, imageUrl: value.image_url, capacity: value.capacity, openingTime: value.opening_time, closingTime: value.closing_time, maxReservationMinutes: value.max_reservation_minutes, requiresApproval: value.requires_approval, status: value.status } }
export function fromApiReservation(value: ApiReservation): Reservation { return { id: asId(value.id), areaId: asId(value.common_area_id), residentId: asId(value.resident_id), date: value.date, startTime: value.start_time ?? undefined, endTime: value.end_time ?? undefined, status: value.status, createdAt: value.created_at, rejectionReason: value.rejection_reason ?? undefined, residentName: value.resident?.name, unitCode: value.resident?.unit?.code } }
export function fromApiOccupancy(value: { date: string; start_time?: string | null; end_time?: string | null }): ReservationOccupancy { return { date: value.date, startTime: value.start_time ?? undefined, endTime: value.end_time ?? undefined } }
function mapPeriod(value: ApiPeriod): AvailabilityPeriod { return { startTime: value.start_time, endTime: value.end_time, minutes: value.minutes } }
export function fromApiAvailability(value: ApiAvailability): AreaAvailability { return { area: fromApiArea(value.common_area), startDate: value.start_date, endDate: value.end_date, slotMinutes: value.slot_minutes, days: value.days.map(day => ({ date: day.date, status: day.status, reason: day.reason, reasonLabel: day.reason_label, fullDayAvailable: day.full_day_available, freePeriods: day.free_periods.map(mapPeriod), occupiedPeriods: day.occupied_periods.map(mapPeriod), slots: day.slots.map(slot => ({ startTime: slot.start_time, endTime: slot.end_time, status: slot.status })) })) } }
function areaBody(input: Partial<CommonAreaInput>) { return { ...(input.name !== undefined ? { name: input.name } : {}), ...(input.description !== undefined ? { description: input.description } : {}), ...(input.imageUrl !== undefined ? { image_url: input.imageUrl } : {}), ...(input.capacity !== undefined ? { capacity: input.capacity } : {}), ...(input.openingTime !== undefined ? { opening_time: input.openingTime } : {}), ...(input.closingTime !== undefined ? { closing_time: input.closingTime } : {}), ...(input.maxReservationMinutes !== undefined ? { max_reservation_minutes: input.maxReservationMinutes } : {}), ...(input.requiresApproval !== undefined ? { requires_approval: input.requiresApproval } : {}), ...(input.status !== undefined ? { status: input.status } : {}) } }
function one<A, B>(response: ApiResponse<A>, mapper: (value: A) => B): ApiResponse<B> { return { ...response, data: mapper(response.data) } }
function many<A, B>(response: ApiResponse<A[]>, mapper: (value: A) => B): ApiResponse<B[]> { return { ...response, data: response.data.map(mapper) } }

export function createApiReservationRepository(): ReservationRepository {
  const request = createApiRequest()
  const api: typeof request = async (path, options) => { try { return await request(path, options) } catch (cause) { if (cause instanceof AppError && cause.code === 'CONFLICT') throw new AppError('RESERVATION_CONFLICT', cause.message, cause.fields); throw cause } }
  const listQuery = (page = 1, filters?: string) => ({ page, limit: 20, ...(filters ? { filters } : {}) })
  const dates = (startDate: string, endDate: string) => ({ start_date: startDate, end_date: endDate })
  return {
    list: async (page, filters) => many(await api<ApiReservation[]>('/reservations', { query: listQuery(page, filters) }), fromApiReservation),
    listMine: async (page, filters) => many(await api<ApiReservation[]>('/reservations/me', { query: listQuery(page, filters) }), fromApiReservation),
    findById: async id => one(await api<ApiReservation>(`/reservations/${id}`), fromApiReservation),
    create: async input => one(await api<ApiReservation>('/reservations', { method: 'POST', body: { common_area_id: Number(input.areaId), date: input.date, ...(input.startTime && input.endTime ? { start_time: input.startTime, end_time: input.endTime } : {}) } }), fromApiReservation),
    cancel: async id => one(await api<ApiReservation>(`/reservations/${id}/cancel`, { method: 'POST' }), fromApiReservation),
    updateStatus: async (id, status: ReservationStatus, rejectionReason?: string) => one(await api<ApiReservation>(`/reservations/${id}/status`, { method: 'PATCH', body: { status, ...(rejectionReason ? { rejection_reason: rejectionReason } : {}) } }), fromApiReservation),
    listAreas: async () => many(await api<ApiArea[]>('/common-areas', { query: { limit: 100 } }), fromApiArea),
    listOccupancy: async (areaId, startDate, endDate) => many(await api<Array<{ date: string; start_time?: string | null; end_time?: string | null }>>(`/common-areas/${areaId}/occupancy`, { query: dates(startDate, endDate) }), fromApiOccupancy),
    availability: async (areaId, startDate, endDate) => one(await api<ApiAvailability>(`/common-areas/${areaId}/availability`, { query: dates(startDate, endDate) }), fromApiAvailability),
    createArea: async input => one(await api<ApiArea>('/common-areas', { method: 'POST', body: areaBody(input) }), fromApiArea),
    updateArea: async (id, input) => one(await api<ApiArea>(`/common-areas/${id}`, { method: 'PATCH', body: areaBody(input) }), fromApiArea),
    removeArea: id => api<null>(`/common-areas/${id}`, { method: 'DELETE' }),
  }
}
