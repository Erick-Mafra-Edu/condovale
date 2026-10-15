import { toAppError, type AppError } from '~/domain/app-error'
import type { PermissionCatalogItem, RolePermissionSummary, UserPermissionSummary } from '~/domain/permission'
import { useServices } from '~/services'

export function usePermissions() {
  const { permissionService } = useServices()
  const catalog = ref<PermissionCatalogItem[]>([])
  const roles = ref<RolePermissionSummary[]>([])
  const selected = ref<UserPermissionSummary | null>(null)
  const loading = ref(false)
  const error = ref<AppError | null>(null)
  async function execute<T>(action: () => Promise<T>) {
    loading.value = true; error.value = null
    try { return await action() } catch (cause) { error.value = toAppError(cause); throw error.value } finally { loading.value = false }
  }
  async function loadCatalog() { catalog.value = (await execute(() => permissionService.list())).data }
  async function loadRoles() { roles.value = (await execute(() => permissionService.roles())).data }
  async function loadUserPermissions(userId: string) { selected.value = (await execute(() => permissionService.forUser(userId))).data; return selected.value }
  async function grant(userId: string, permission: string) { const result = await execute(() => permissionService.grant(userId, permission)); if (selected.value?.userId === userId) await loadUserPermissions(userId); return result.data }
  async function revoke(userId: string, permission: string) { const result = await execute(() => permissionService.revoke(userId, permission)); if (selected.value?.userId === userId) await loadUserPermissions(userId); return result.data }
  return { catalog, roles, selected, loading, error, loadCatalog, loadRoles, loadUserPermissions, grant, revoke }
}

