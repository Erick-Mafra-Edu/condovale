import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApiPermissionRepository } from '~/repositories/api/api-permission-repository'

describe('contratos API de permissões', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('mapeia snake_case das concessões individuais', async () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({ data: {
      user_id: 7,
      role: 'syndic',
      role_label: 'Síndico',
      from_role: ['login'],
      direct: ['close-any-occurrence'],
      effective: ['close-any-occurrence', 'login'],
    }, message: null }))

    await expect(createApiPermissionRepository().forUser('7')).resolves.toMatchObject({
      data: { userId: '7', roleLabel: 'Síndico', fromRole: ['login'], direct: ['close-any-occurrence'] },
    })
  })

  it('envia concessão e revogação para as rotas reais', async () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    const fetch = vi.fn().mockResolvedValue({ data: { user_id: 7, direct: [] }, message: null })
    vi.stubGlobal('$fetch', fetch)
    const repository = createApiPermissionRepository()

    await repository.grant('7', 'close-any-occurrence')
    await repository.revoke('7', 'close-any-occurrence')
    expect(fetch).toHaveBeenNthCalledWith(1, '/users/7/permissions', expect.objectContaining({ method: 'POST', body: { permission: 'close-any-occurrence' } }))
    expect(fetch).toHaveBeenNthCalledWith(2, '/users/7/permissions/close-any-occurrence', expect.objectContaining({ method: 'DELETE' }))
  })
})

