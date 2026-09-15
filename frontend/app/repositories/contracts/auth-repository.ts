import type { AuthSession, LoginInput } from '~/domain/auth'
import type { ApiResponse } from '~/domain/common'

export interface AuthRepository {
  authenticate(input: LoginInput): Promise<ApiResponse<AuthSession>>
  currentSession(): Promise<ApiResponse<AuthSession | null>>
  logout(): Promise<ApiResponse<null>>
}
