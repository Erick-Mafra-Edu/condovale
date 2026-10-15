import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApiUnitOccupancyRepository } from '~/repositories/api/api-unit-occupancy-repository'

describe('contrato paginado de ocupações', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('agrega todas as páginas', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 1, user_id: 2, unit_id: 3, occupant_type: 'owner', started_at: '2026-01-01', ended_at: null, is_active: true }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 4, user_id: 5, unit_id: 6, occupant_type: 'tenant', started_at: '2026-01-02', ended_at: null, is_active: true }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', fetch)

    const result = await createApiUnitOccupancyRepository().list()
    expect(result.data.map(item => [item.id, item.user_id, item.unit_id])).toEqual([['1', '2', '3'], ['4', '5', '6']])
    expect(fetch).toHaveBeenNthCalledWith(2, '/unit-occupancies', expect.objectContaining({ query: { relations: 'user;unit', page: 2 } }))
  })

  it('encerra o vínculo pelo endpoint DELETE com a data no formato do Laravel', async () => {
    const fetch = vi.fn().mockResolvedValue({
      data: { id: 1, user_id: 2, unit_id: 3, occupant_type: 'tenant', started_at: '2026-01-01', ended_at: '2026-02-15', is_active: false },
      message: 'Vínculo encerrado.',
    })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', fetch)

    const result = await createApiUnitOccupancyRepository().remove('1', '2026-02-15')

    expect(result.data).toMatchObject({ id: '1', ended_at: '2026-02-15', is_active: false })
    expect(fetch).toHaveBeenCalledWith('/unit-occupancies/1', expect.objectContaining({
      method: 'DELETE',
      body: { ended_at: '2026-02-15' },
    }))
  })
})
