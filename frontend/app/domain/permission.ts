import type { UseCase } from './permissions'

export interface PermissionCatalogItem {
  id?: string | number
  name: UseCase
}

export interface RolePermissionSummary {
  name: string
  permissions: UseCase[]
}

export interface UserPermissionSummary {
  userId: string
  role: string
  roleLabel: string
  fromRole: UseCase[]
  direct: UseCase[]
  effective: UseCase[]
}

