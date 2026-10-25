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
    await expect(mockOccurrenceRepository.assign(item.id, 'user-employee-security')).rejects.toMatchObject({ code: 'VALIDATION_ERROR' })
  })

  it('recusa encaminhamento fora de análise ou para usuário inativo', async () => {
    const item = occurrence()
    setMockAuthenticatedUserId('user-admin')
    await expect(mockOccurrenceRepository.assign(item.id, 'user-employee')).rejects.toMatchObject({ code: 'CONFLICT' })
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

  it('permite ao responsável repassar chamados atribuídos ou em andamento com justificativa', async () => {
    for (const status of ['assigned', 'in_progress'] as const) {
      const item = occurrence(status)
      item.assignedEmployeeId = 'user-employee'
      setMockAuthenticatedUserId('user-employee')

      await mockOccurrenceRepository.assign(item.id, 'user-employee-security', 'Necessário redirecionar para a equipe de segurança')

      expect(item).toMatchObject({ status: 'assigned', assignedEmployeeId: 'user-employee-security' })
    }
  })

  it('exclui a própria imagem e deixa a gestão excluir qualquer uma', async () => {
    const item = occurrence('in_progress')
    item.assignedEmployeeId = 'user-employee'
    const file = new File(['imagem'], 'errada.jpg', { type: 'image/jpeg' })

    setMockAuthenticatedUserId('user-01')
    const doMorador = (await mockOccurrenceRepository.uploadAttachment(item.id, file)).data
    setMockAuthenticatedUserId('user-employee')
    const doFuncionario = (await mockOccurrenceRepository.uploadAttachment(item.id, file)).data

    await expect(mockOccurrenceRepository.deleteAttachment(item.id, doMorador.id)).rejects.toMatchObject({ code: 'FORBIDDEN' })
    await mockOccurrenceRepository.deleteAttachment(item.id, doFuncionario.id)

    setMockAuthenticatedUserId('user-syndic')
    await mockOccurrenceRepository.deleteAttachment(item.id, doMorador.id)

    expect((await mockOccurrenceRepository.listAttachments(item.id)).data).toEqual([])
    expect((await mockOccurrenceRepository.history(item.id)).data.map(entry => entry.message)).toContain('Imagem removida: errada.jpg.')
  })

  it('não exclui imagem de chamado encerrado', async () => {
    const item = occurrence()
    setMockAuthenticatedUserId('user-01')
    const attachment = (await mockOccurrenceRepository.uploadAttachment(item.id, new File(['imagem'], 'evidencia.jpg', { type: 'image/jpeg' }))).data
    item.status = 'completed'

    setMockAuthenticatedUserId('user-admin')
    await expect(mockOccurrenceRepository.deleteAttachment(item.id, attachment.id)).rejects.toMatchObject({ code: 'CONFLICT' })
  })

  it('lista para abertura só moradores ativos com unidade', async () => {
    const result = await mockOccurrenceRepository.listOpenableResidents()

    expect(result.data).toEqual([{ id: 'user-01', name: 'Morador exemplo' }])
  })
})
