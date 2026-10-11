import { toAppError, type AppError } from '~/domain/app-error'
import type { CreateUnitInput, Unit, UpdateUnitInput } from '~/domain/user'
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

  async function createUnit(input: CreateUnitInput) {
    const result = await execute(() => unitService.create(input))
    units.value.push(result.data)
    return result.data
  }

  async function updateUnit(id: string, input: UpdateUnitInput) {
    const result = await execute(() => unitService.update(id, input))
    const index = units.value.findIndex(item => item.id === id)
    if (index >= 0) units.value[index] = result.data
    return result.data
  }

  async function deactivateUnit(id: string) {
    const result = await execute(() => unitService.deactivate(id))
    const index = units.value.findIndex(item => item.id === id)
    if (index >= 0) units.value[index] = result.data
    return result.data
  }

  return { units, loading, error, loadUnits, findUnit, createUnit, updateUnit, deactivateUnit }
}

