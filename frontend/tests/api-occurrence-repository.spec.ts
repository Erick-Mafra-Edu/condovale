import { describe, expect, it, vi, afterEach } from 'vitest'
import { createApiOccurrenceRepository, fromApiOccurrence, fromApiOccurrenceHistory } from '../app/repositories/api/api-occurrence-repository'

describe('cliente API de ocorrências', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('converte respostas Laravel snake_case para o domínio camelCase', () => {
    expect(fromApiOccurrence({ id: '1', title: 'Falha', description: 'Detalhes', category: 'Manutenção', resident_id: 'resident-1', unit_id: 'unit-1', assigned_employee_id: 'employee-1', status: 'completed', created_at: '2026-10-05', updated_at: '2026-10-06', completed_at: '2026-10-06' })).toMatchObject({ residentId: 'resident-1', unitId: 'unit-1', assignedEmployeeId: 'employee-1', createdAt: '2026-10-05', updatedAt: '2026-10-06', completedAt: '2026-10-06' })
    expect(fromApiOccurrenceHistory({ id: 'history-1', occurrence_id: '1', type: 'completed', user_id: 'employee-1', previous_status: 'in_progress', new_status: 'completed', created_at: '2026-10-06' })).toMatchObject({ occurrenceId: '1', userId: 'employee-1', previousStatus: 'in_progress', newStatus: 'completed', createdAt: '2026-10-06' })
  })

  it('envia apenas o contrato Laravel e usa a sessão como identidade', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: {}, message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    vi.stubGlobal('$fetch', fetch)
    const repository = createApiOccurrenceRepository()

    await repository.create({ title: 'Falha', description: 'Detalhes', category: 'Manutenção' })
    await repository.assign('occurrence-1', 'employee-1')
    await repository.updateStatus('occurrence-1', 'in_progress')
    await repository.finish('occurrence-1', 'Concluído')

    expect(fetch).toHaveBeenNthCalledWith(1, '/occurrences', expect.objectContaining({ body: { title: 'Falha', description: 'Detalhes', category: 'Manutenção' } }))
    expect(fetch).toHaveBeenNthCalledWith(2, '/occurrences/occurrence-1/assign', expect.objectContaining({ body: { employee_id: 'employee-1' } }))
    expect(fetch).toHaveBeenNthCalledWith(3, '/occurrences/occurrence-1/status', expect.objectContaining({ body: { status: 'in_progress' } }))
    expect(fetch).toHaveBeenNthCalledWith(4, '/occurrences/occurrence-1/finish', expect.objectContaining({ body: { message: 'Concluído' } }))
  })
})
