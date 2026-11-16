import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApiUnitRepository } from '~/repositories/api/api-unit-repository'
import { stubApiFetch } from './api-fetch-mock'

describe('repositório API de unidades', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('agrega páginas e normaliza os IDs', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 1, code: 'A-101', number: '101', block: null, status: 'active' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 2, code: 'A-102', number: '102', block: 'A', status: 'inactive' }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)

    const result = await createApiUnitRepository().list()

    expect(result.data).toEqual([
      { id: '1', code: 'A-101', number: '101', block: undefined, status: 'active' },
      { id: '2', code: 'A-102', number: '102', block: 'A', status: 'inactive' },
    ])
    expect(fetch).toHaveBeenNthCalledWith(2, '/units', expect.objectContaining({ query: { page: 2 } }))
  })
})
