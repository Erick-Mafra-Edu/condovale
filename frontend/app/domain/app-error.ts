export class AppError extends Error {
  constructor(public readonly code: string, message: string, public readonly fields?: Record<string, string[]>) {
    super(message)
    this.name = 'AppError'
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

export function toAppError(cause: unknown): AppError {
  if (cause instanceof AppError) return cause

  const root = isRecord(cause) ? cause : {}
  const response = isRecord(root.response) ? root.response : {}
  const data = isRecord(root.data) ? root.data : isRecord(response._data) ? response._data : {}
  const message = typeof data.message === 'string' && data.message.trim().length > 0 && data.message.trim().length <= 240
    ? data.message.trim()
    : 'Não foi possível concluir a operação'
  const code = typeof data.code === 'string' ? data.code : 'SERVER_ERROR'
  return new AppError(code, message)
}
