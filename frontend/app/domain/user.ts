export type UserRole = 'resident' | 'employee' | 'admin' | 'syndic'
export type UserStatus = 'active' | 'inactive'

export interface User {
  id: string
  name: string
  email: string
  role: UserRole
  status: UserStatus
  unitId?: string
  avatarUrl?: string
}

export interface Unit {
  id: string
  number: string
  block?: string
  status: UserStatus
}

export type CreateUserInput = Omit<User, 'id'>
export type UpdateUserInput = Partial<CreateUserInput>
