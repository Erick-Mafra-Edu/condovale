import { toAppError, type AppError } from '~/domain/app-error'
import type { CreateUnitOccupancyInput, UnitOccupancy } from '~/domain/unit-occupancy'
import { useServices } from '~/services'

export function useUnitOccupancies() {
  const { unitOccupancyService } = useServices()
  const occupancies = ref<UnitOccupancy[]>([])
  const loading = ref(false)
  const error = ref<AppError | null>(null)

  async function execute<T>(action: () => Promise<T>): Promise<T> {
    loading.value = true
    error.value = null
    try { return await action() }
    catch (cause) { const failure = toAppError(cause); error.value = failure; throw failure }
    finally { loading.value = false }
  }

  async function loadOccupancies() {
    const result = await execute(() => unitOccupancyService.list())
    occupancies.value = result.data
  }

  async function findOccupancy(id: string) {
    const result = await execute(() => unitOccupancyService.findById(id))
    return result.data
  }

  async function createOccupancy(input: CreateUnitOccupancyInput) {
    const result = await execute(() => unitOccupancyService.create(input))
    occupancies.value.push(result.data)
    return result.data
  }

  async function removeOccupancy(id: string, endedAt?: string) {
    const result = await execute(() => unitOccupancyService.remove(id, endedAt))
    const index = occupancies.value.findIndex(item => item.id === id)
    if (index >= 0) occupancies.value[index] = result.data
    return result.data
  }

  return { occupancies, loading, error, loadOccupancies, findOccupancy, createOccupancy, removeOccupancy }
}
