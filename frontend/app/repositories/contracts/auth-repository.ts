import type { AuthSession, ChangePasswordInput, LoginInput } from '~/domain/auth'
import type { ApiResponse } from '~/domain/common'
import type { User } from '~/domain/user'

export interface AuthRepository {
  authenticate(input: LoginInput): Promise<ApiResponse<AuthSession>>
  currentSession(): Promise<ApiResponse<AuthSession | null>>
  changePassword(input: ChangePasswordInput): Promise<ApiResponse<User>>
  logout(): Promise<ApiResponse<null>>
}
