import type { AuthSession, ChangePasswordInput, LoginInput } from '~/domain/auth'
import type { AuthRepository } from '~/repositories/contracts/auth-repository'
import { createApiRequest } from './request'
import { fromApiUser } from './api-user-repository'
import type { ApiResponse } from '~/domain/common'

type ApiSession = { user: Parameters<typeof fromApiUser>[0] }
function mapSession(response: ApiResponse<ApiSession>): ApiResponse<AuthSession> {
  return { ...response, data: { user: fromApiUser(response.data.user) } }
}

function mapOptionalSession(response: ApiResponse<ApiSession | null>): ApiResponse<AuthSession | null> {
  return { ...response, data: response.data?.user ? { user: fromApiUser(response.data.user) } : null }
}

/** O backend deve definir o cookie HttpOnly de sessão; nenhum token é salvo no User. */
export function createApiAuthRepository(): AuthRepository {
  const api = createApiRequest()
  const sessionOptions = { credentials: 'include' as const }

  return {
    authenticate: async input => mapSession(await api<ApiSession>('/auth/login', { method: 'POST', body: input, ...sessionOptions })),
    currentSession: async () => mapOptionalSession(await api<ApiSession | null>('/auth/session', sessionOptions)),
    changePassword: async (input: ChangePasswordInput) => {
      const response = await api<Parameters<typeof fromApiUser>[0]>('/users/me/password', { method: 'PATCH', body: input, ...sessionOptions })
      return { ...response, data: fromApiUser(response.data) }
    },
    logout: () => api<null>('/auth/logout', { method: 'POST', ...sessionOptions }),
  }
}
