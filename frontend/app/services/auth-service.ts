import { AppError } from '~/domain/app-error'
import { changePasswordInputSchema, loginInputSchema, type ChangePasswordInput, type LoginInput } from '~/domain/auth'
import type { AuthRepository } from '~/repositories/contracts/auth-repository'

function validate(input: LoginInput): LoginInput {
  const result = loginInputSchema.safeParse(input)
  if (result.success) return result.data
  const fields = Object.fromEntries(
    Object.entries(result.error.flatten().fieldErrors)
      .filter((entry): entry is [string, string[]] => Array.isArray(entry[1]) && entry[1].length > 0),
  )
  throw new AppError('VALIDATION_ERROR', 'Revise as credenciais', fields)
}

function validateChangePassword(input: ChangePasswordInput): ChangePasswordInput {
  const result = changePasswordInputSchema.safeParse(input)
  if (result.success) return result.data
  const fields = Object.fromEntries(Object.entries(result.error.flatten().fieldErrors).filter((entry): entry is [string, string[]] => Array.isArray(entry[1]) && entry[1].length > 0))
  throw new AppError('VALIDATION_ERROR', 'Revise os campos informados', fields)
}

export function createAuthService(repository: AuthRepository) {
  return {
    async authenticate(input: LoginInput) {
      return repository.authenticate(validate(input))
    },
    currentSession: () => repository.currentSession(),
    async changePassword(input: ChangePasswordInput) {
      return repository.changePassword(validateChangePassword(input))
    },
    logout: () => repository.logout(),
  }
}
