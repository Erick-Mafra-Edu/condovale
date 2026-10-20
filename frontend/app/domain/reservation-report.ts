import type { Reservation, ReservationStatus } from './reservation'

export type ReservationReportStatus = ReservationStatus | 'all'

export interface ReservationReportFilters {
  from: string
  to: string
  status: ReservationReportStatus
}

export interface ReservationReportQuery {
  filters: ReservationReportFilters
  pagination: { page: number; perPage: number }
  exportAll?: boolean
}

export function filterReservations(reservations: Reservation[], filters: ReservationReportFilters) {
  return reservations.filter(item => {
    const inPeriod = item.date >= filters.from && item.date <= filters.to
    const matchesStatus = filters.status === 'all' || item.status === filters.status
    return inPeriod && matchesStatus
  })
}

export function paginateReservations(reservations: Reservation[], page: number, perPage: number) {
  if (perPage <= 0) return reservations
  return reservations.slice((page - 1) * perPage, page * perPage)
}
