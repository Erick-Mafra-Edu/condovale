export class AppError extends Error {
  constructor(public readonly code: string, message: string, public readonly fields?: Record<string, string[]>) {
    super(message)
    this.name = 'AppError'
  }
}

export function toAppError(cause: unknown): AppError {
  return cause instanceof AppError ? cause : new AppError('SERVER_ERROR', 'Não foi possível concluir a operação')
}
