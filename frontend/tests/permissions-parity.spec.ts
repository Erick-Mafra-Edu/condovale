import { describe, expect, it } from 'vitest'
import { rolePermissions, type UseCase } from '~/domain/permissions'

describe('paridade da matriz de permissões', () => {
  it('mantém as concessões administrativas de ocorrências do Laravel', () => {
    const expected: Record<'syndic' | 'admin', UseCase[]> = {
      syndic: ['open-occurrence-for-resident', 'manage-occurrence-types', 'close-any-occurrence'],
      admin: ['open-occurrence-for-resident', 'manage-occurrence-types', 'close-any-occurrence'],
    }

    for (const [role, permissions] of Object.entries(expected) as Array<[keyof typeof expected, UseCase[]]>) {
      for (const permission of permissions) expect(rolePermissions[role].has(permission)).toBe(true)
    }
  })
})

