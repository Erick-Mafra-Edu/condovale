import { AppError } from '~/domain/app-error'
import type { AuthSession, LoginInput } from '~/domain/auth'
import type { ApiResponse } from '~/domain/common'
import type { AuthRepository } from '~/repositories/contracts/auth-repository'
import { mockUsers } from './mock-user-repository'
import { simulateRequest } from './mock-config'
import { setMockAuthenticatedUserId } from './mock-session'

let session: AuthSession | null = null

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

/** Mock somente para desenvolvimento; validação de senha real é exclusiva do backend. */
export const mockAuthRepository: AuthRepository = {
  async authenticate(input: LoginInput) {
    await simulateRequest()
    const user = mockUsers.find(item => item.email === input.email && item.status === 'active')
    if (!user || input.password !== 'condovale') throw new AppError('UNAUTHENTICATED', 'E-mail ou senha inválidos')
    session = { user }
    setMockAuthenticatedUserId(user.id)
    return response(session)
  },
  async currentSession() {
    await simulateRequest()
    return response(session)
  },
  async logout() {
    await simulateRequest()
    session = null
    setMockAuthenticatedUserId(null)
    return response(null)
  },
}
