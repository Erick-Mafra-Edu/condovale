import type { AuthSession, LoginInput } from '~/domain/auth'
import type { AuthRepository } from '~/repositories/contracts/auth-repository'
import { createApiRequest } from './request'

/** O backend deve definir o cookie HttpOnly de sessão; nenhum token é salvo no User. */
export function createApiAuthRepository(): AuthRepository {
  const api = createApiRequest()
  const sessionOptions = { credentials: 'include' as const }

  return {
    authenticate: (input: LoginInput) => api<AuthSession>('/auth/login', { method: 'POST', body: input, ...sessionOptions }),
    currentSession: () => api<AuthSession | null>('/auth/session', sessionOptions),
    logout: () => api<null>('/auth/logout', { method: 'POST', ...sessionOptions }),
  }
}
