import type { UserRole } from './user'

/** Casos de uso definidos no diagrama funcional do CondoVale. */
export type UseCase =
  | 'login'
  | 'update-own-profile'
  | 'view-notices'
  | 'create-occurrence'
  | 'track-own-occurrences'
  | 'view-common-areas'
  | 'request-reservation'
  | 'view-own-reservations'
  | 'cancel-own-reservation'
  | 'manage-units'
  | 'manage-residents'
  | 'link-residents-to-units'
  | 'analyze-occurrences'
  | 'assign-occurrence'
  | 'publish-notices'
  | 'manage-reservations'
  | 'approve-or-reject-reservation'
  | 'view-assigned-occurrences'
  | 'update-occurrence-progress'
  | 'finish-occurrence'
  | 'generate-reports'

export const rolePermissions: Readonly<Record<UserRole, ReadonlySet<UseCase>>> = {
  resident: new Set([
    'login', 'update-own-profile', 'view-notices', 'create-occurrence', 'track-own-occurrences',
    'view-common-areas', 'request-reservation', 'view-own-reservations', 'cancel-own-reservation',
  ]),
  employee: new Set(['login', 'view-assigned-occurrences', 'update-occurrence-progress', 'finish-occurrence']),
  syndic: new Set(['login', 'publish-notices', 'generate-reports']),
  admin: new Set([
    'login', 'manage-units', 'manage-residents', 'link-residents-to-units', 'analyze-occurrences',
    'assign-occurrence', 'publish-notices', 'manage-reservations', 'approve-or-reject-reservation',
  ]),
}

export function can(role: UserRole, useCase: UseCase): boolean {
  return rolePermissions[role].has(useCase)
}
