import type { ApiResponse } from '~/domain/common'
import type { PermissionCatalogItem, RolePermissionSummary, UserPermissionSummary } from '~/domain/permission'

export interface PermissionRepository {
  list(): Promise<ApiResponse<PermissionCatalogItem[]>>
  roles(): Promise<ApiResponse<RolePermissionSummary[]>>
  forUser(userId: string): Promise<ApiResponse<UserPermissionSummary>>
  grant(userId: string, permission: string): Promise<ApiResponse<Pick<UserPermissionSummary, 'userId' | 'direct'>>>
  revoke(userId: string, permission: string): Promise<ApiResponse<Pick<UserPermissionSummary, 'userId' | 'direct'>>>
}

