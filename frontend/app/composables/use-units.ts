import { toAppError, type AppError } from '~/domain/app-error'
import type { Unit } from '~/domain/user'
import { useServices } from '~/services'

export function useUnits() {
  const { unitService } = useServices()
  const units = ref<Unit[]>([])
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

  async function loadUnits() {
    const result = await execute(() => unitService.list())
    units.value = result.data
  }

  async function findUnit(id: string) {
    const result = await execute(() => unitService.findById(id))
    return result.data
  }

  return { units, loading, error, loadUnits, findUnit }
}

