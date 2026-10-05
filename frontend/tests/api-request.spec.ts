import { describe, expect, it, vi } from 'vitest'
import { createApiRequest } from '../app/repositories/api/request'

describe('cliente HTTP da API', () => {
  it('envia sessão por padrão e preserva override explícito', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: [], message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', fetch)

    const api = createApiRequest()
    await api('/users')
    await api('/public', { credentials: 'omit' })

    expect(fetch).toHaveBeenNthCalledWith(1, '/users', { baseURL: '/api', credentials: 'include' })
    expect(fetch).toHaveBeenNthCalledWith(2, '/public', { baseURL: '/api', credentials: 'omit' })
    vi.unstubAllGlobals()
  })
})
