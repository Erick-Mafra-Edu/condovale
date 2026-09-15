import { toAppError, type AppError } from '~/domain/app-error'
import type { User, CreateUserInput, UpdateUserInput } from '~/domain/user'
import { useServices } from '~/services'

export function useUsers() {
  const { userService } = useServices()
  const users = ref<User[]>([])
  const loading = ref(false)
  const error = ref<AppError | null>(null)

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

  async function loadUsers() {
    const result = await execute(() => userService.list())
    users.value = result.data
  }

  async function findUser(id: string) {
    const result = await execute(() => userService.findById(id))
    return result.data
  }

  async function createUser(input: CreateUserInput) {
    const result = await execute(() => userService.create(input))
    users.value.push(result.data)
    return result.data
  }

  async function updateUser(id: string, input: UpdateUserInput) {
    const result = await execute(() => userService.update(id, input))
    const index = users.value.findIndex(item => item.id === id)
    if (index >= 0) users.value[index] = result.data
    return result.data
  }

  async function deactivateUser(id: string) {
    const result = await execute(() => userService.deactivate(id))
    const index = users.value.findIndex(item => item.id === id)
    if (index >= 0) users.value[index] = result.data
    return result.data
  }

  return { users, loading, error, loadUsers, findUser, createUser, updateUser, deactivateUser }
}

