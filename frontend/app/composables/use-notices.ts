import { toAppError, type AppError } from '~/domain/app-error'
import type { Notice, CreateNoticeInput, UpdateNoticeInput } from '~/domain/notice'
import { useServices } from '~/services'

export function useNotices() {
  const { noticeService } = useServices()
  const notices = ref<Notice[]>([])
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

  async function loadNotices() {
    const result = await execute(() => noticeService.list())
    notices.value = result.data
  }

  async function findNotice(id: string) {
    const result = await execute(() => noticeService.findById(id))
    return result.data
  }

  async function createNotice(input: CreateNoticeInput) {
    const result = await execute(() => noticeService.create(input))
    notices.value.push(result.data)
    return result.data
  }

  async function updateNotice(id: string, input: UpdateNoticeInput) {
    const result = await execute(() => noticeService.update(id, input))
    const index = notices.value.findIndex(item => item.id === id)
    if (index >= 0) notices.value[index] = result.data
    return result.data
  }

  async function removeNotice(id: string) {
    await execute(() => noticeService.remove(id))
    notices.value = notices.value.filter(item => item.id !== id)
  }

  return { notices, loading, error, loadNotices, findNotice, createNotice, updateNotice, removeNotice }
}

