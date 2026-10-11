import { describe, expect, it, vi } from 'vitest'
import { mockUnitOccupancyRepository } from '../app/repositories/mock/mock-unit-occupancy-repository'
import { mockUnitRepository } from '../app/repositories/mock/mock-unit-repository'
import { createUnitService } from '../app/services/unit-service'
import type { UnitRepository } from '../app/repositories/contracts/unit-repository'

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

describe('gerência de unidades', () => {
  it('cria, atualiza e inativa uma unidade no mock', async () => {
    const created = await mockUnitRepository.create({ code: 'B-202', number: '202', block: 'B', status: 'active' })
    expect((await mockUnitRepository.findById(created.data.id)).data).toEqual(created.data)

    const updated = await mockUnitRepository.update(created.data.id, { number: '203' })
    expect(updated.data.number).toBe('203')

    const deactivated = await mockUnitRepository.deactivate(created.data.id)
    expect(deactivated.data.status).toBe('inactive')
  })

  it('rejeita código e número vazios sem chamar o repository', async () => {
    const repository = { create: vi.fn() } as unknown as UnitRepository
    const service = createUnitService(repository)

    await expect(service.create({ code: '  ', number: '  ', status: 'active' })).rejects.toMatchObject({ code: 'VALIDATION_ERROR' })
    expect(repository.create).not.toHaveBeenCalled()
  })
})
