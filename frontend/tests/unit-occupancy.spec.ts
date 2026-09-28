import { describe, expect, it } from 'vitest'
import { mockUnitOccupancyRepository } from '../app/repositories/mock/mock-unit-occupancy-repository'

describe('vínculo entre morador e unidade', () => {
  it('cria, lista, consulta e encerra um vínculo', async () => {
    const input = { user_id: 'user-test', unit_id: 'unit-test', occupant_type: 'tenant' as const, started_at: '2026-09-01' }
    const created = await mockUnitOccupancyRepository.create(input)

    expect((await mockUnitOccupancyRepository.list()).data).toContainEqual(created.data)
    expect((await mockUnitOccupancyRepository.findById(created.data.id)).data).toEqual(created.data)

    const ended = await mockUnitOccupancyRepository.remove(created.data.id, '2026-09-30')
    expect(ended.data).toMatchObject({ id: created.data.id, ended_at: '2026-09-30', is_active: false })
  })
})
