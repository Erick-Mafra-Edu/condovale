import { describe, expect, it } from 'vitest'
import { toApiUser } from '~/repositories/api/api-user-repository'
import { fromApiOccupancy, fromApiReservation } from '~/repositories/api/api-reservation-repository'

describe('contratos API Laravel', () => {
  it('converte usuário para snake_case sem campos camelCase', () => {
    expect(toApiUser({ name: 'Ana', email: 'ana@example.com', role: 'resident', unitId: '7', avatarUrl: '/ana.png' })).toEqual({
      name: 'Ana', email: 'ana@example.com', role: 'resident', unit_id: '7', avatar_url: '/ana.png',
    })
  })

  it('converte reserva e ocupação para o modelo da tela', () => {
    expect(fromApiReservation({ id: '1', common_area_id: '2', resident_id: '3', date: '2026-10-05', start_time: '10:00', end_time: '11:00', status: 'approved', created_at: '2026-10-01' })).toMatchObject({ areaId: '2', residentId: '3', startTime: '10:00', endTime: '11:00', createdAt: '2026-10-01' })
    expect(fromApiOccupancy({ date: '2026-10-05', start_time: '10:00', end_time: '11:00' })).toEqual({ date: '2026-10-05', startTime: '10:00', endTime: '11:00' })
  })
})
