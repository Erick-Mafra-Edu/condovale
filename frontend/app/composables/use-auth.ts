import { toAppError, type AppError } from '~/domain/app-error'
import type { LoginInput } from '~/domain/auth'
import type { User } from '~/domain/user'
import { useServices } from '~/services'

/** Estado de sessão da UI; credenciais não permanecem após authenticate(). */
export function useAuth() {
  const { authService } = useServices()
  const user = useState<User | null>('auth-user', () => null)
  const loading = useState('auth-loading', () => false)
  const error = useState<AppError | null>('auth-error', () => null)

  async function execute<T>(action: () => Promise<T>): Promise<T> {
    loading.value = true
    error.value = null
    try { return await action() }
    catch (cause) {
      const failure = toAppError(cause)
      error.value = failure
      throw failure
    } finally { loading.value = false }
  }

  async function authenticate(input: LoginInput) {
    const result = await execute(() => authService.authenticate(input))
    user.value = result.data.user
    return user.value
  }

  async function restoreSession() {
    const result = await execute(() => authService.currentSession())
    user.value = result.data?.user ?? null
    return user.value
  }

  async function logout() {
    await execute(() => authService.logout())
    user.value = null
  }

  return { user, loading, error, authenticate, restoreSession, logout }
}
