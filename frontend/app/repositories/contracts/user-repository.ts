import type { ApiResponse } from '~/domain/common'
import type { User, CreateUserInput, UpdateUserInput } from '~/domain/user'

export interface UserRepository {
  list(): Promise<ApiResponse<User[]>>
  findById(id: string): Promise<ApiResponse<User>>
  create(input: CreateUserInput): Promise<ApiResponse<User>>
  update(id: string, input: UpdateUserInput): Promise<ApiResponse<User>>
  deactivate(id: string): Promise<ApiResponse<User>>
}

