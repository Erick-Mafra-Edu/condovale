import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

export function createApiRequest() {
  const { public: config } = useRuntimeConfig()
  return async <T>(path: string, options?: Parameters<typeof $fetch>[1]): Promise<ApiResponse<T>> => {
    try {
      return await $fetch<ApiResponse<T>>(path, { baseURL: config.apiBase, ...options })
    } catch (cause) {
      if (cause instanceof AppError) throw cause
      const error = isRecord(cause) ? cause : {}
      const response = isRecord(error.response) ? error.response : {}
      const status = error.statusCode ?? error.status ?? response.status
      const data = isRecord(error.data) ? error.data : {}
      const code = status === 422 ? 'VALIDATION_ERROR' : status === 409 ? 'CONFLICT' : status === 404 ? 'NOT_FOUND' : typeof status === 'number' ? 'SERVER_ERROR' : 'NETWORK_ERROR'
      const rawFields = data.fields ?? data.errors
      const fields = isRecord(rawFields)
        ? Object.fromEntries(Object.entries(rawFields).filter((entry): entry is [string, string[]] => Array.isArray(entry[1]) && entry[1].every(value => typeof value === 'string')))
        : undefined
      throw new AppError(typeof data.code === 'string' ? data.code : code,
        typeof data.message === 'string' ? data.message : code === 'NETWORK_ERROR' ? 'Não foi possível conectar ao servidor' : 'Não foi possível concluir a operação', fields)
    }
  }
}
