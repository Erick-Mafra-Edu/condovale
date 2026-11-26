import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

export function createApiRequest() {
  const { public: config } = useRuntimeConfig()
  return async <T>(path: string, options?: Parameters<typeof $fetch>[1]): Promise<ApiResponse<T>> => {
    try {
      const response = await $fetch.raw<ApiResponse<T>>(path, { baseURL: config.apiBase, credentials: 'include', ...options })
      const responseData: unknown = response._data

      if (response.headers.get('content-type')?.includes('text/html')
        && typeof responseData === 'string'
        && responseData.includes('/aes.js')) {
        throw new AppError('HOST_CHALLENGE', 'A sessão da hospedagem expirou. Recarregue a página, entre novamente e selecione a imagem outra vez.')
      }

      return responseData as ApiResponse<T>
    } catch (cause) {
      if (cause instanceof AppError) throw cause
      const error = isRecord(cause) ? cause : {}
      const response = isRecord(error.response) ? error.response : {}
      const status = error.statusCode ?? error.status ?? response.status
      if (status === 401 && import.meta.client) window.dispatchEvent(new Event('condovale-session-expired'))
      const data = isRecord(error.data) ? error.data : {}
      const code = status === 401 ? 'UNAUTHENTICATED' : status === 403 ? 'FORBIDDEN' : status === 404 ? 'NOT_FOUND' : status === 409 ? 'CONFLICT' : status === 422 ? 'VALIDATION_ERROR' : status === 429 ? 'RATE_LIMITED' : typeof status === 'number' ? 'SERVER_ERROR' : 'NETWORK_ERROR'
      const rawFields = data.fields ?? data.errors
      const fields = isRecord(rawFields)
        ? Object.fromEntries(Object.entries(rawFields).filter((entry): entry is [string, string[]] => Array.isArray(entry[1]) && entry[1].every(value => typeof value === 'string')))
        : undefined
      const fallback = ({ UNAUTHENTICATED: 'Sua sessão expirou. Entre novamente.', FORBIDDEN: 'Você não tem permissão para esta ação.', NOT_FOUND: 'Registro inexistente ou indisponível.', CONFLICT: 'Os dados foram alterados. Atualize e tente novamente.', VALIDATION_ERROR: 'Revise os campos informados.', RATE_LIMITED: 'Muitas tentativas. Aguarde e tente novamente.', SERVER_ERROR: 'Ocorreu um erro inesperado.', NETWORK_ERROR: 'Não foi possível conectar ao servidor.' } as Record<string, string>)[code]
      throw new AppError(typeof data.code === 'string' ? data.code : code,
        typeof data.message === 'string' && status !== 500 ? data.message : fallback ?? 'Não foi possível concluir a operação.', fields)
    }
  }
}
