import { describe, expect, it } from 'vitest'
import { asId } from '~/domain/common'
import { fromApiArea, fromApiAvailability, fromApiReservation } from '~/repositories/api/api-reservation-repository'
import { canViewModule } from '~/domain/permissions'

describe('contratos das sprints 5 e 6', () => {
  it('normaliza IDs e a reserva administrativa sem expor dados extras', () => {
    expect(asId(42)).toBe('42')
    expect(fromApiReservation({ id: 7, common_area_id: 3, resident_id: 8, date: '2026-12-01', start_time: null, end_time: null, status: 'pending', created_at: '2026-11-26T12:00:00Z', resident: { name: 'Ana', unit: { code: 'A-101' } } })).toMatchObject({ id: '7', areaId: '3', residentId: '8', residentName: 'Ana', unitCode: 'A-101', startTime: undefined })
  })

  it('preserva 24:00 e usa a disponibilidade calculada pelo servidor', () => {
    const area = { id: 3, name: 'Salão', opening_time: '08:00', closing_time: '24:00', requires_approval: true, status: 'available' as const }
    expect(fromApiArea(area).closingTime).toBe('24:00')
    const result = fromApiAvailability({ common_area: area, start_date: '2026-12-01', end_date: '2026-12-01', slot_minutes: 30, days: [{ date: '2026-12-01', status: 'partial', reason: null, reason_label: null, full_day_available: false, free_periods: [{ start_time: '23:30', end_time: '24:00', minutes: 30 }], occupied_periods: [{ start_time: '08:00', end_time: '09:00' }], slots: [{ start_time: '23:30', end_time: '24:00', status: 'available' }] }] })
    expect(result.days[0]).toMatchObject({ status: 'partial', fullDayAvailable: false, freePeriods: [{ endTime: '24:00' }], slots: [{ endTime: '24:00', status: 'available' }] })
  })

  it('mostra gestão de áreas para concessão individual', () => {
    expect(canViewModule('resident', 'Áreas comuns', ['manage-reservations'])).toBe(true)
    expect(canViewModule('resident', 'Áreas comuns', [])).toBe(false)
  })
})
