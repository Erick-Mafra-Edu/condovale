import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApiAuthRepository } from '~/repositories/api/api-auth-repository'
import { createApiUserRepository } from '~/repositories/api/api-user-repository'

describe('contratos API de autenticação e usuários', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('normaliza o usuário retornado no login', async () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', vi.fn().mockResolvedValue({ data: { user: { id: 4, name: 'Ana', email: 'ana@example.com', role: 'resident', status: 'active', unit_id: null, must_change_password: true } }, message: null }))

    const result = await createApiAuthRepository().authenticate({ email: 'ana@example.com', password: 'secret' })
    expect(result.data.user).toMatchObject({ id: '4', unitId: undefined, mustChangePassword: true })
  })

  it('envia a troca de senha e normaliza o usuário retornado', async () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    const fetch = vi.fn().mockResolvedValue({ data: { id: 4, name: 'Ana', email: 'ana@example.com', role: 'resident', status: 'active', must_change_password: false }, message: 'Senha alterada.' })
    vi.stubGlobal('$fetch', fetch)

    const result = await createApiAuthRepository().changePassword({ current_password: 'temporary', password: 'new-password', password_confirmation: 'new-password' })
    expect(result.data).toMatchObject({ id: '4', mustChangePassword: false })
    expect(fetch).toHaveBeenCalledWith('/users/me/password', expect.objectContaining({ method: 'PATCH', body: { current_password: 'temporary', password: 'new-password', password_confirmation: 'new-password' } }))
  })

  it('agrega páginas de usuários e desembrulha o cadastro', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 1, name: 'A', email: 'a@example.com', role: 'resident', status: 'active' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 2, name: 'B', email: 'b@example.com', role: 'resident', status: 'active' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: { user: { id: 3, name: 'C', email: 'c@example.com', role: 'resident', status: 'active' }, temporary_password: 'temp' }, message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', fetch)

    const repository = createApiUserRepository()
    expect((await repository.list()).data.map(user => user.id)).toEqual(['1', '2'])
    const created = await repository.create({ name: 'C', email: 'c@example.com', role: 'resident', status: 'active' })
    expect(created.data).toMatchObject({ user: { id: '3' }, temporaryPassword: 'temp' })
    expect(fetch).toHaveBeenNthCalledWith(2, '/users', expect.objectContaining({ query: { page: 2 } }))
  })
})
