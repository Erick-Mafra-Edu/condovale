import { describe, expect, it } from 'vitest'
import { toApiUser } from '~/repositories/api/api-user-repository'
import { fromApiOccupancy, fromApiReservation } from '~/repositories/api/api-reservation-repository'
import { fromApiUnit } from '~/repositories/api/api-unit-repository'
import { fromApiUnitOccupancy } from '~/repositories/api/api-unit-occupancy-repository'
import { fromApiUser } from '~/repositories/api/api-user-repository'

describe('contratos API Laravel', () => {
  it('converte usuário para snake_case sem campos camelCase', () => {
    expect(toApiUser({ name: 'Ana', email: 'ana@example.com', role: 'resident', unitId: '7', avatarUrl: '/ana.png', mustChangePassword: true })).toEqual({
      name: 'Ana', email: 'ana@example.com', role: 'resident', unit_id: '7', avatar_url: '/ana.png',
    })
  })

  it('converte reserva e ocupação para o modelo da tela', () => {
    expect(fromApiReservation({ id: '1', common_area_id: '2', resident_id: '3', date: '2026-10-05', start_time: '10:00', end_time: '11:00', status: 'approved', created_at: '2026-10-01' })).toMatchObject({ areaId: '2', residentId: '3', startTime: '10:00', endTime: '11:00', createdAt: '2026-10-01' })
    expect(fromApiOccupancy({ date: '2026-10-05', start_time: '10:00', end_time: '11:00' })).toEqual({ date: '2026-10-05', startTime: '10:00', endTime: '11:00' })
  })

  it('normaliza IDs Laravel e bloco nulo de unidades e ocupações', () => {
    const unit = fromApiUnit({ id: 7, code: 'A-101', number: '101', block: null, status: 'active' })
    expect(unit).toMatchObject({ id: '7', block: undefined })

    const occupancy = fromApiUnitOccupancy({ id: 8, user_id: 9, unit_id: 7, occupant_type: 'owner', started_at: '2026-10-01', ended_at: null, is_active: true, unit: { id: 7, code: 'A-101', number: '101', block: null, status: 'active' } })
    expect(occupancy).toMatchObject({ id: '8', user_id: '9', unit_id: '7', unit: { id: '7', block: undefined } })
  })

  it('normaliza usuário Laravel com ID numérico e unidade nula', () => {
    expect(fromApiUser({ id: 9, name: 'Ana', email: 'ana@example.com', role: 'resident', status: 'active', unit_id: null })).toMatchObject({ id: '9', unitId: undefined })
  })

  it('normaliza a relação de unidade retornada pela sessão', () => {
    expect(fromApiUser({ id: 9, name: 'Ana', email: 'ana@example.com', role: 'resident', status: 'active', unit_id: 7, unit: { id: 7, code: 'B-204', number: '204', block: 'B', status: 'active' } })).toMatchObject({ unitId: '7', unit: { id: '7', code: 'B-204', number: '204', block: 'B' } })
  })
})
