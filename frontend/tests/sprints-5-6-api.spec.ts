import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApiReservationRepository } from '~/repositories/api/api-reservation-repository'
import { createApiNoticeRepository } from '~/repositories/api/api-notice-repository'
import { createApiReportRepository } from '~/repositories/api/api-report-repository'
import { createApiAuditReportRepository } from '~/repositories/api/api-audit-report-repository'
import { stubApiFetch } from './api-fetch-mock'

describe('requisições API das sprints 5 e 6', () => {
  afterEach(() => vi.unstubAllGlobals())
  function setup(data: unknown) {
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    const fetch = vi.fn().mockResolvedValue({ data, message: null })
    stubApiFetch(fetch)
    return fetch
  }

  it('envia reserva sem identidade do solicitante e suporta 24:00', async () => {
    const fetch = setup({ id: 1, common_area_id: 2, resident_id: 3, date: '2026-12-01', status: 'pending', created_at: '2026-11-26T12:00:00Z' })
    const repository = createApiReservationRepository()
    await repository.create({ areaId: '2', date: '2026-12-01', startTime: '23:30', endTime: '24:00' })
    expect(fetch).toHaveBeenCalledWith('/reservations', expect.objectContaining({ method: 'POST', body: { common_area_id: 2, date: '2026-12-01', start_time: '23:30', end_time: '24:00' } }))
  })

  it('usa a publicação posterior sem autor ou data no payload', async () => {
    const fetch = setup({ id: 1, title: 'Aviso', content: 'Texto', author_id: 3, published_at: null, status: 'draft' })
    const repository = createApiNoticeRepository()
    await repository.create({ title: 'Aviso', content: 'Texto', status: 'draft' })
    await repository.publish('1')
    expect(fetch).toHaveBeenNthCalledWith(1, '/notices', expect.objectContaining({ body: { title: 'Aviso', content: 'Texto', status: 'draft' } }))
    expect(fetch).toHaveBeenNthCalledWith(2, '/notices/1/publish', expect.objectContaining({ method: 'POST' }))
  })

  it('solicita exportação completa nos endpoints de relatórios e auditoria', async () => {
    const fetch = setup({})
    await createApiReportRepository().occurrences({ from: '2026-11-01', to: '2026-11-30', export: true })
    await createApiReportRepository().reservations({ from: '2026-11-01', to: '2026-11-30', export: true })
    await createApiAuditReportRepository().get({ from: '2026-11-01', to: '2026-11-30', export: true })
    expect(fetch.mock.calls.map(call => call[0])).toEqual(['/reports/occurrences', '/reports/reservations', '/audit-logs'])
    expect(fetch.mock.calls.every(call => call[1]?.query?.export === true)).toBe(true)
  })
})
