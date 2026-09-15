import { AppError } from '~/domain/app-error'
import type { CreateUserInput, UpdateUserInput } from '~/domain/user'
import type { UserRepository } from '~/repositories/contracts/user-repository'

function validate(input: UpdateUserInput) {
  const fields: Record<string, string[]> = {}
  if (input.name !== undefined && !input.name.trim()) fields.name = ['Informe o nome']
  if (input.email !== undefined && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.email)) fields.email = ['Informe um e-mail válido']
  if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise os campos informados', fields)
}

export function createUserService(repository: UserRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    async create(input: CreateUserInput) {
      validate(input)
      return repository.create(input)
    },
    async update(id: string, input: UpdateUserInput) {
      validate(input)
      return repository.update(id, input)
    },
    deactivate: (id: string) => repository.deactivate(id),
  }
}

