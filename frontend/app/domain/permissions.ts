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
  | 'open-occurrence-for-resident'
  | 'manage-occurrence-types'
  | 'analyze-occurrences'
  | 'assign-occurrence'
  | 'hand-over-occurrence'
  | 'publish-notices'
  | 'manage-reservations'
  | 'approve-or-reject-reservation'
  | 'view-assigned-occurrences'
  | 'update-occurrence-progress'
  | 'finish-occurrence'
  | 'close-any-occurrence'
  | 'generate-reports'
  | 'view-audit-reports'

export const rolePermissions: Readonly<Record<UserRole, ReadonlySet<UseCase>>> = {
  resident: new Set([
    'login', 'update-own-profile', 'view-notices', 'create-occurrence', 'track-own-occurrences',
    'view-common-areas', 'request-reservation', 'view-own-reservations', 'cancel-own-reservation',
  ]),
  employee: new Set(['login', 'update-own-profile', 'view-assigned-occurrences', 'update-occurrence-progress', 'finish-occurrence', 'hand-over-occurrence']),
  syndic: new Set(['login', 'update-own-profile', 'manage-units', 'open-occurrence-for-resident', 'manage-occurrence-types', 'close-any-occurrence', 'publish-notices', 'generate-reports']),
  admin: new Set([
    'login', 'update-own-profile', 'manage-units', 'manage-residents', 'link-residents-to-units', 'analyze-occurrences',
    'assign-occurrence', 'open-occurrence-for-resident', 'manage-occurrence-types', 'close-any-occurrence', 'publish-notices', 'manage-reservations', 'approve-or-reject-reservation',
    'generate-reports', 'view-audit-reports',
  ]),
}

export function can(role: UserRole, useCase: UseCase, effectivePermissions?: readonly UseCase[]): boolean {
  return effectivePermissions ? effectivePermissions.includes(useCase) : rolePermissions[role].has(useCase)
}

export type AppModule = 'Início' | 'Meu cadastro' | 'Usuários' | 'Tipos de ocorrência' | 'Ocorrências' | 'Reservas' | 'Comunicados' | 'Relatórios' | 'Unidades' | 'Moradores e unidades'

const modulePermissions: Readonly<Record<Exclude<AppModule, 'Início'>, readonly UseCase[]>> = {
  'Meu cadastro': ['update-own-profile'],
  Usuários: ['manage-residents'],
  'Tipos de ocorrência': ['manage-occurrence-types'],
  Ocorrências: ['create-occurrence', 'open-occurrence-for-resident', 'track-own-occurrences', 'view-assigned-occurrences', 'analyze-occurrences', 'assign-occurrence'],
  Reservas: ['view-common-areas', 'request-reservation', 'view-own-reservations', 'manage-reservations', 'approve-or-reject-reservation'],
  Comunicados: ['view-notices', 'publish-notices'],
  Relatórios: ['generate-reports', 'view-audit-reports'],
  Unidades: ['manage-units'],
  'Moradores e unidades': ['link-residents-to-units'],
}

export function canViewModule(role: UserRole, module: AppModule, effectivePermissions?: readonly UseCase[]): boolean {
  return module === 'Início' || modulePermissions[module].some(useCase => can(role, useCase, effectivePermissions))
}
