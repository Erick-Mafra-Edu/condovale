import { describe, expect, it, vi, afterEach } from 'vitest'
import { createApiOccurrenceTypeRepository, fromApiOccurrenceType } from '../app/repositories/api/api-occurrence-type-repository'
import { stubApiFetch } from './api-fetch-mock'

describe('cliente API de tipos de ocorrência', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('converte o identificador e employee_can_close para camelCase', () => {
    expect(fromApiOccurrenceType({ id: 7, name: 'Vazamento', description: null, employee_can_close: true, active: true })).toMatchObject({ id: '7', employeeCanClose: true })
  })

  it('envia os campos do contrato Laravel', async () => {
    const fetch = vi.fn().mockResolvedValue({ data: { id: 1, name: 'Vazamento', employee_can_close: false, active: true }, message: null })
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '/api' } }))
    stubApiFetch(fetch)
    const repository = createApiOccurrenceTypeRepository()
    await repository.create({ name: 'Vazamento', description: 'Água', employeeCanClose: false, active: true })
    expect(fetch).toHaveBeenCalledWith('/occurrence-types', expect.objectContaining({ method: 'POST', body: { name: 'Vazamento', description: 'Água', employee_can_close: false, active: true } }))
  })
})
