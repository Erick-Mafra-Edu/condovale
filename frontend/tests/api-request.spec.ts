import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApiRequest } from '../app/repositories/api/request'

describe('cliente HTTP da API', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('envia sessão por padrão e preserva override explícito', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: [], message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', { raw: vi.fn(async (...args) => ({ _data: await fetch(...args), headers: new Headers({ 'content-type': 'application/json' }) })) })

    const api = createApiRequest()
    await api('/users')
    await api('/public', { credentials: 'omit' })

    expect(fetch).toHaveBeenNthCalledWith(1, '/users', { baseURL: '/api', credentials: 'include' })
    expect(fetch).toHaveBeenNthCalledWith(2, '/public', { baseURL: '/api', credentials: 'omit' })
  })

  it('identifica o desafio HTML da hospedagem antes de tratar a resposta como JSON', async () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', { raw: vi.fn().mockResolvedValue({
      _data: '<script src="/aes.js"></script>',
      headers: new Headers({ 'content-type': 'text/html; charset=UTF-8' }),
    }) })

    await expect(createApiRequest()('/occurrences/1/attachments', { method: 'POST' })).rejects.toMatchObject({
      code: 'HOST_CHALLENGE',
      message: 'A sessão da hospedagem expirou. Recarregue a página, entre novamente e selecione a imagem outra vez.',
    })
  })
})
