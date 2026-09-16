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

export type AppModule = 'Início' | 'Ocorrências' | 'Reservas' | 'Comunicados' | 'Relatórios'

const modulePermissions: Readonly<Record<Exclude<AppModule, 'Início'>, readonly UseCase[]>> = {
  Ocorrências: ['create-occurrence', 'track-own-occurrences', 'view-assigned-occurrences', 'analyze-occurrences', 'assign-occurrence'],
  Reservas: ['view-common-areas', 'request-reservation', 'view-own-reservations', 'manage-reservations', 'approve-or-reject-reservation'],
  Comunicados: ['view-notices', 'publish-notices'],
  Relatórios: ['generate-reports'],
}

export function canViewModule(role: UserRole, module: AppModule): boolean {
  return module === 'Início' || modulePermissions[module].some(useCase => can(role, useCase))
}
