export type UserRole = 'resident' | 'employee' | 'admin' | 'syndic'
export type UserStatus = 'active' | 'inactive'
import type { UseCase } from './permissions'

export interface User {
  id: string
  name: string
  email: string
  role: UserRole
  status: UserStatus
  mustChangePassword?: boolean
  unitId?: string
  unit?: Unit
  avatarUrl?: string
  /** Effective permissions when the session endpoint exposes them. */
  permissions?: UseCase[]
}

export interface Unit {
  id: string
  code: string
  number: string
  block?: string
  status: UserStatus
}

export type CreateUnitInput = Omit<Unit, 'id' | 'status'> & { status?: UserStatus }
export type UpdateUnitInput = Partial<CreateUnitInput>

export type CreateUserInput = Omit<User, 'id' | 'mustChangePassword'>
export type UpdateUserInput = Partial<CreateUserInput>
export type UpdateOwnProfileInput = Pick<User, 'name' | 'email'>

export interface UserCreationResult {
  user: User
  temporaryPassword: string
}
