import { afterEach, describe, expect, it } from 'vitest'
import type { Occurrence } from '../app/domain/occurrence'
import { mockOccurrences } from '../app/repositories/mock/state'
import { mockOccurrenceRepository } from '../app/repositories/mock/mock-occurrence-repository'
import { mockUsers } from '../app/repositories/mock/mock-user-repository'
import { setMockAuthenticatedUserId } from '../app/repositories/mock/mock-session'

const created: Occurrence[] = []

afterEach(() => {
  for (const occurrence of created) mockOccurrences.splice(mockOccurrences.indexOf(occurrence), 1)
  setMockAuthenticatedUserId(null)
})

function occurrence(status: Occurrence['status'] = 'open'): Occurrence {
  const item: Occurrence = { id: `sprint3-${crypto.randomUUID()}`, title: 'Falha', description: 'Detalhes', category: 'Manutenção', residentId: 'user-01', status, createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() }
  mockOccurrences.push(item)
  created.push(item)
  return item
}

describe('fluxo Sprint 3 de ocorrências', () => {
  it('analisa e encaminha uma ocorrência uma única vez', async () => {
    const item = occurrence()
    setMockAuthenticatedUserId('user-admin')
    await mockOccurrenceRepository.updateStatus(item.id, 'analysis')
    setMockAuthenticatedUserId('user-admin')
    await mockOccurrenceRepository.assign(item.id, 'user-employee')
    expect(item).toMatchObject({ status: 'assigned', assignedEmployeeId: 'user-employee' })
    await expect(mockOccurrenceRepository.assign(item.id, 'user-employee-security')).rejects.toMatchObject({ code: 'FORBIDDEN' })
  })

  it('recusa encaminhamento fora de análise ou para usuário inativo', async () => {
    const item = occurrence()
    setMockAuthenticatedUserId('user-admin')
    await expect(mockOccurrenceRepository.assign(item.id, 'user-employee')).rejects.toMatchObject({ code: 'FORBIDDEN' })
    const employee = mockUsers.find(user => user.id === 'user-employee')!
    employee.status = 'inactive'
    await mockOccurrenceRepository.updateStatus(item.id, 'analysis')
    await expect(mockOccurrenceRepository.assign(item.id, employee.id)).rejects.toMatchObject({ code: 'FORBIDDEN' })
    employee.status = 'active'
  })

  it('completa US05-US07 na mesma fonte mock', async () => {
    const repository = mockOccurrenceRepository
    setMockAuthenticatedUserId('user-01')
    const createdItem = (await repository.create({ title: 'Vazamento', description: 'Garagem', occurrenceTypeId: '1' })).data
    created.push(createdItem)
    expect((await repository.list()).data.some(item => item.id === createdItem.id)).toBe(true)

    setMockAuthenticatedUserId('user-admin')
    await repository.updateStatus(createdItem.id, 'analysis')
    await repository.assign(createdItem.id, 'user-employee')
    setMockAuthenticatedUserId('user-employee')
    await repository.updateStatus(createdItem.id, 'in_progress')
    await repository.finish(createdItem.id, 'Atendimento concluído')

    const finalItem = (await repository.findById(createdItem.id)).data
    const history = (await repository.history(createdItem.id)).data
    expect(finalItem.status).toBe('completed')
    expect(history.map(entry => entry.newStatus)).toEqual(['open', 'analysis', 'assigned', 'in_progress', 'completed'])
  })
})
