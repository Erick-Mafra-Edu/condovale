import { describe, expect, it } from 'vitest'
import { toAppError } from '~/domain/app-error'

describe('toAppError', () => {
  it('preserves a safe server message from the API payload', () => {
    const error = toAppError({ data: { code: 'VALIDATION_ERROR', message: 'E-mail já cadastrado.' } })
    expect(error.code).toBe('VALIDATION_ERROR')
    expect(error.message).toBe('E-mail já cadastrado.')
  })

  it('falls back when the payload has no safe message', () => {
    expect(toAppError({ message: 'internal stack details' }).message).toBe('Não foi possível concluir a operação')
    expect(toAppError({ data: { message: '   ' } }).message).toBe('Não foi possível concluir a operação')
  })
})
