import { describe, expect, it, vi, afterEach } from 'vitest'
import { createApiOccurrenceRepository, fromApiOccurrence, fromApiOccurrenceHistory } from '../app/repositories/api/api-occurrence-repository'
import { stubApiFetch } from './api-fetch-mock'

describe('cliente API de ocorrências', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('converte respostas Laravel snake_case para o domínio camelCase', () => {
    expect(fromApiOccurrence({ id: '1', title: 'Falha', description: 'Detalhes', category: 'Manutenção', resident_id: 'resident-1', unit_id: 'unit-1', assigned_employee_id: 'employee-1', status: 'completed', created_at: '2026-10-05', updated_at: '2026-10-06', completed_at: '2026-10-06' })).toMatchObject({ residentId: 'resident-1', unitId: 'unit-1', assignedEmployeeId: 'employee-1', createdAt: '2026-10-05', updatedAt: '2026-10-06', completedAt: '2026-10-06' })
    expect(fromApiOccurrenceHistory({ id: 'history-1', occurrence_id: '1', type: 'completed', user_id: 'employee-1', previous_status: 'in_progress', new_status: 'completed', created_at: '2026-10-06' })).toMatchObject({ occurrenceId: '1', userId: 'employee-1', previousStatus: 'in_progress', newStatus: 'completed', createdAt: '2026-10-06' })
  })

  it('envia apenas o contrato Laravel e usa a sessão como identidade', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: {}, message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    await repository.create({ title: 'Falha', description: 'Detalhes', occurrenceTypeId: 1 })
    await repository.updateStatus('occurrence-1', 'in_progress')
    await repository.finish('occurrence-1', { resolved: true, conclusion: 'Concluído' })

    expect(fetch).toHaveBeenNthCalledWith(1, '/occurrences', expect.objectContaining({ body: { title: 'Falha', description: 'Detalhes', occurrence_type_id: 1 } }))
    expect(fetch).toHaveBeenNthCalledWith(2, '/occurrences/occurrence-1/status', expect.objectContaining({ body: { status: 'in_progress' } }))
    expect(fetch).toHaveBeenNthCalledWith(3, '/occurrences/occurrence-1/finish', expect.objectContaining({ body: { resolved: true, conclusion: 'Concluído' } }))
  })

  it('lista todas as páginas e mapeia o tipo aninhado da ocorrência', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 1, title: 'Falha', description: 'Detalhes', status: 'open', resident_id: 7, occurrence_type: { id: 3, name: 'Manutenção' }, created_at: '2026-10-05', updated_at: '2026-10-05' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 2, title: 'Ruído', description: 'Detalhes', status: 'analysis', resident_id: 8, occurrence_type: { id: 4, name: 'Barulho' }, created_at: '2026-10-06', updated_at: '2026-10-06' }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    const result = await repository.list()

    expect(result.data).toMatchObject([
      { id: '1', residentId: '7', occurrenceTypeId: '3', occurrenceTypeName: 'Manutenção', category: 'Manutenção' },
      { id: '2', residentId: '8', occurrenceTypeId: '4', occurrenceTypeName: 'Barulho', category: 'Barulho' },
    ])
    expect(fetch).toHaveBeenNthCalledWith(2, '/occurrences', expect.objectContaining({ query: { page: 2 } }))
  })

  it('consulta detalhe com id codificado', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: { id: 'oc/1', title: 'Falha', description: 'Detalhes', status: 'open', resident_id: 7, created_at: '2026-10-05', updated_at: '2026-10-05' }, message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    await repository.findById('oc/1')

    expect(fetch).toHaveBeenCalledWith('/occurrences/oc%2F1', expect.objectContaining({ baseURL: '/api', credentials: 'include' }))
  })

  it('lista histórico paginado e mapeia autoria e estados', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 1, occurrence_id: 9, type: 'created', user_id: 7, user: { id: 7, name: 'Ana' }, new_status: 'open', created_at: '2026-10-05' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 2, occurrence_id: 9, type: 'status_changed', user_id: 8, user: { id: 8, name: 'Pedro' }, previous_status: 'open', new_status: 'analysis', created_at: '2026-10-06' }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    const result = await repository.history('9')

    expect(result.data).toMatchObject([
      { id: '1', occurrenceId: '9', userId: '7', userName: 'Ana', newStatus: 'open' },
      { id: '2', occurrenceId: '9', userId: '8', userName: 'Pedro', previousStatus: 'open', newStatus: 'analysis' },
    ])
    expect(fetch).toHaveBeenNthCalledWith(2, '/occurrences/9/history', expect.objectContaining({ query: { page: 2 } }))
  })

  it('atribui por PATCH somente com employee_id', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: { id: 9, title: 'Falha', description: 'Detalhes', status: 'assigned', assigned_employee_id: 8, resident_id: 7, created_at: '2026-10-05', updated_at: '2026-10-06' }, message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    await repository.assign('9', '8')

    expect(fetch).toHaveBeenCalledWith('/occurrences/9/assign', expect.objectContaining({ method: 'PATCH', body: { employee_id: 8 } }))
  })

  it('lista funcionários atribuíveis em todas as páginas e mapeia IDs', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 8, name: 'Pedro', email: 'pedro@example.com', role: 'employee', status: 'active' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 9, name: 'Ana', email: 'ana@example.com', role: 'employee', status: 'active' }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    const result = await repository.listAssignableEmployees()

    expect(result.data).toEqual([
      expect.objectContaining({ id: '8', name: 'Pedro' }),
      expect.objectContaining({ id: '9', name: 'Ana' }),
    ])
    expect(fetch).toHaveBeenNthCalledWith(2, '/assignable-employees', expect.objectContaining({ query: { page: 2 } }))
  })

  it('atualiza o atendimento com status e mensagem pelo endpoint de attendance', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: { id: 9, title: 'Falha', description: 'Detalhes', status: 'in_progress', resident_id: 7, created_at: '2026-10-05', updated_at: '2026-10-06' }, message: 'Andamento registrado.' })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    const result = await repository.updateAttendance('9', { status: 'in_progress', message: 'Equipe no local.' })

    expect(result.message).toBe('Andamento registrado.')
    expect(result.data.status).toBe('in_progress')
    expect(fetch).toHaveBeenCalledWith('/occurrences/9', expect.objectContaining({ method: 'PATCH', body: { status: 'in_progress', message: 'Equipe no local.' } }))
  })

  it('lista anexos paginados e mapeia os campos snake_case', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 1, occurrence_id: 9, uploaded_by: 7, original_name: 'foto.jpg', mime_type: 'image/jpeg', size: 1200, width: 640, height: 480, url: '/download/1' }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 2, occurrence_id: 9, uploaded_by: 8, original_name: 'video.png', mime_type: 'image/png', size: 800, url: '/download/2' }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    const result = await repository.listAttachments('9')

    expect(result.data).toMatchObject([
      { id: '1', occurrenceId: '9', uploadedBy: '7', originalName: 'foto.jpg', mimeType: 'image/jpeg', width: 640, height: 480 },
      { id: '2', occurrenceId: '9', uploadedBy: '8', originalName: 'video.png', mimeType: 'image/png' },
    ])
    expect(fetch).toHaveBeenNthCalledWith(2, '/occurrences/9/attachments', expect.objectContaining({ query: { page: 2 } }))
  })

  it('envia anexo como multipart image sem fixar Content-Type', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: { id: 1, occurrence_id: 9, uploaded_by: 7, original_name: 'foto.jpg', mime_type: 'image/jpeg', size: 1200, url: '/download/1' }, message: 'Imagem anexada.' })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()
    const file = new File(['imagem'], 'foto.jpg', { type: 'image/jpeg' })

    const result = await repository.uploadAttachment('9', file)
    const options = fetch.mock.calls[0][1]
    const body = options.body as FormData

    expect(result.data).toMatchObject({ id: '1', occurrenceId: '9', uploadedBy: '7', originalName: 'foto.jpg', mimeType: 'image/jpeg' })
    expect(body).toBeInstanceOf(FormData)
    expect(body.get('image')).toBe(file)
    expect(options.headers).toBeUndefined()
  })

  it('exclui anexo pelo DELETE do próprio chamado', async () => {
    const fetch = vi.fn().mockResolvedValue({ status: true })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    await repository.deleteAttachment('9', '3')

    expect(fetch).toHaveBeenCalledWith('/occurrences/9/attachments/3', expect.objectContaining({ method: 'DELETE' }))
  })

  it('lista os moradores da abertura em todas as páginas, com a unidade', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce({ data: [{ id: 5, name: 'Ana Silva', unit_id: 1, unit: { id: 1, code: 'A-101' } }], message: null, total: 2, amount: 1 })
      .mockResolvedValueOnce({ data: [{ id: 6, name: 'Bruno Costa', unit_id: 2, unit: null }], message: null, total: 2, amount: 1 })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceRepository()

    const result = await repository.listOpenableResidents({ search: 'a' })

    expect(result.data).toEqual([
      { id: '5', name: 'Ana Silva', unitCode: 'A-101' },
      { id: '6', name: 'Bruno Costa', unitCode: undefined },
    ])
    expect(fetch).toHaveBeenNthCalledWith(1, '/occurrence-residents', expect.objectContaining({ query: { search: 'a' } }))
    expect(fetch).toHaveBeenNthCalledWith(2, '/occurrence-residents', expect.objectContaining({ query: { search: 'a', page: 2 } }))
  })

  it('monta a URL protegida do anexo', () => {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    const repository = createApiOccurrenceRepository()

    expect(repository.attachmentUrl('oc/9', 'file/1')).toBe('/api/occurrences/oc%2F9/attachments/file%2F1')
  })
})
