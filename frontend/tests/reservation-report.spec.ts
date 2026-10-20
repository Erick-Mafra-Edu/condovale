import { describe, expect, it } from 'vitest'
import type { Reservation } from '../app/domain/reservation'
import { filterReservations, paginateReservations } from '../app/domain/reservation-report'

const reservations: Reservation[] = [
  { id: '1', areaId: 'hall', residentId: 'u1', date: '2026-09-02', startTime: '10:00', endTime: '11:00', status: 'approved', createdAt: '2026-08-20T10:00:00Z' },
  { id: '2', areaId: 'gym', residentId: 'u2', date: '2026-09-15', status: 'pending', createdAt: '2026-08-21T10:00:00Z' },
  { id: '3', areaId: 'hall', residentId: 'u3', date: '2026-10-01', status: 'cancelled', createdAt: '2026-08-22T10:00:00Z' },
]

describe('relatório de reservas', () => {
  it('filtra por período e status', () => {
    expect(filterReservations(reservations, { from: '2026-09-01', to: '2026-09-30', status: 'pending' }).map(item => item.id)).toEqual(['2'])
  })

  it('pagina e preserva todos os registros na exportação', () => {
    expect(paginateReservations(reservations, 2, 1).map(item => item.id)).toEqual(['2'])
    expect(paginateReservations(reservations, 1, 0)).toHaveLength(3)
  })
})
