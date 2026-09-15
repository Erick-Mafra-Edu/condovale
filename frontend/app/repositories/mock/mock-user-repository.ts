import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { User } from '~/domain/user'
import type { UserRepository } from '~/repositories/contracts/user-repository'
import { simulateRequest } from './mock-config'

export const mockUsers: User[] = [
  { id: 'user-01', name: 'Morador exemplo', email: 'morador@example.com', role: 'resident', status: 'active', unitId: 'unit-01' },
  { id: 'user-admin', name: 'Administração', email: 'admin@example.com', role: 'admin', status: 'active' },
]

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensureUser(id: string): User {
  const item = mockUsers.find(item => item.id === id)
  if (!item) throw new AppError('NOT_FOUND', 'Usuário não encontrado')
  return item
}

export const mockUserRepository: UserRepository = {
  async list() {
    await simulateRequest()
    return response(mockUsers)
  },
  async findById(id) {
    await simulateRequest()
    return response(ensureUser(id))
  },
  async create(input) {
    await simulateRequest()
    const item: User = { ...structuredClone(input), id: crypto.randomUUID() }
    mockUsers.push(item)
    return response(item)
  },
  async update(id, input) {
    await simulateRequest()
    const item = ensureUser(id)
    Object.assign(item, structuredClone(input))
    return response(item)
  },
  async deactivate(id) {
    await simulateRequest()
    const item = ensureUser(id)
    item.status = 'inactive'
    return response(item)
  },
}
