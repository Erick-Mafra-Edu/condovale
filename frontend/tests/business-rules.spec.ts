import { afterEach, describe, expect, it, vi } from 'vitest'
import { AppError } from '../app/domain/app-error'
import type { ApiResponse } from '../app/domain/common'
import type { CreateNoticeInput, Notice } from '../app/domain/notice'
import type { CreateOccurrenceInput, Occurrence, OccurrenceHistory } from '../app/domain/occurrence'
import type { CommonArea, CreateReservationInput } from '../app/domain/reservation'
import type { NoticeRepository } from '../app/repositories/contracts/notice-repository'
import type { OccurrenceRepository } from '../app/repositories/contracts/occurrence-repository'
import type { ReservationRepository } from '../app/repositories/contracts/reservation-repository'
import { createNoticeService } from '../app/services/notice-service'
import { createOccurrenceService } from '../app/services/occurrence-service'
import { createReservationService } from '../app/services/reservation-service'
import { createAuthService } from '../app/services/auth-service'
import { mockAuthRepository } from '../app/repositories/mock/mock-auth-repository'
import { mockConfig } from '../app/repositories/mock/mock-config'
import { mockReservations } from '../app/repositories/mock/state'
import { mockReservationRepository } from '../app/repositories/mock/mock-reservation-repository'
import { can, canViewModule, rolePermissions, type UseCase } from '../app/domain/permissions'
import type { UserRole } from '../app/domain/user'

const response = <T>(data: T): ApiResponse<T> => ({ data, message: null })

const area: CommonArea = {
  id: 'area-test',
  name: 'Área de teste',
  openingTime: '08:00',
  closingTime: '22:00',
  requiresApproval: false,
  status: 'available',
}

const reservationInput: CreateReservationInput = {
  areaId: area.id,
  residentId: 'user-01',
  date: '2026-09-30',
  startTime: '10:00',
  endTime: '12:00',
}

async function expectAppError(action: () => Promise<unknown>, code: string) {
  const failure = action()
  await expect(failure).rejects.toBeInstanceOf(AppError)
  await expect(failure).rejects.toMatchObject({ code })
}

afterEach(() => {
  mockConfig.latency = 0
  mockConfig.shouldFail = false
})

describe('RN01 e RN08 — reservas', () => {
  it('recusa sobreposição de reservas que bloqueiam a agenda', async () => {
    const initial = mockReservations.length
    const first = await mockReservationRepository.create(reservationInput)

    await expect(mockReservationRepository.create({
      ...reservationInput,
      startTime: '11:00',
      endTime: '13:00',
    })).rejects.toMatchObject({ code: 'RESERVATION_CONFLICT' })

    mockReservations.splice(initial, mockReservations.length - initial)
    expect(first.data.areaId).toBe(area.id)
  })

  it('libera o período quando a reserva é cancelada', async () => {
    const initial = mockReservations.length
    const created = await mockReservationRepository.create(reservationInput)
    await mockReservationRepository.cancel(created.data.id)

    const available = await mockReservationRepository.getBlockingReservations(area.id, reservationInput.date)
    expect(available.data.some(item => item.id === created.data.id)).toBe(false)

    mockReservations.splice(initial, mockReservations.length - initial)
  })
})

describe('validações de aplicação', () => {
  it('RN02 valida campos e intervalo de horário da reserva', async () => {
    const repository = { create: vi.fn() } as unknown as ReservationRepository
    const service = createReservationService(repository)

    await expect(service.reserve({ ...reservationInput, startTime: '12:00', endTime: '10:00' }))
      .rejects.toMatchObject({ code: 'VALIDATION_ERROR', fields: { startTime: ['Informe um intervalo de horário válido'] } })
    expect(repository.create).not.toHaveBeenCalled()
  })

  it('RN05 exige que a ocorrência informe o morador responsável', async () => {
    const repository = { create: vi.fn() } as unknown as OccurrenceRepository
    const service = createOccurrenceService(repository)
    const input = { title: 'Falha', description: 'Detalhes', category: 'Manutenção', residentId: '' } as CreateOccurrenceInput

    await expect(service.create(input)).rejects.toMatchObject({ code: 'VALIDATION_ERROR' })
    expect(repository.create).not.toHaveBeenCalled()
  })

  it('valida título e conteúdo de comunicado', async () => {
    const repository = { create: vi.fn() } as unknown as NoticeRepository
    const service = createNoticeService(repository)
    const input = { title: ' ', content: '', authorId: 'user-01', publishedAt: '', status: 'draft' } as CreateNoticeInput

    await expect(service.create(input)).rejects.toMatchObject({
      code: 'VALIDATION_ERROR',
      fields: { title: ['Informe o título'], content: ['Informe o conteúdo'] },
    })
    expect(repository.create).not.toHaveBeenCalled()
  })
})

describe('RN06 — histórico de ocorrência', () => {
  it('exige usuário nas operações de atribuição, status e conclusão', async () => {
    const repository = {
      assign: vi.fn(),
      updateStatus: vi.fn(),
      finish: vi.fn(),
    } as unknown as OccurrenceRepository
    const service = createOccurrenceService(repository)

    await expectAppError(() => service.assign('occurrence-01', 'employee-01', ''), 'VALIDATION_ERROR')
    await expectAppError(() => service.updateStatus('occurrence-01', 'in_progress', ''), 'VALIDATION_ERROR')
    await expectAppError(() => service.finish('occurrence-01', ''), 'VALIDATION_ERROR')
    expect(repository.assign).not.toHaveBeenCalled()
    expect(repository.updateStatus).not.toHaveBeenCalled()
    expect(repository.finish).not.toHaveBeenCalled()
  })

  it('encaminha autoria explícita para registrar alterações', async () => {
    const occurrence: Occurrence = {
      id: 'occurrence-01', title: 'Falha', description: 'Detalhes', category: 'Manutenção',
      residentId: 'user-01', status: 'open', createdAt: '', updatedAt: '',
    }
    const history: OccurrenceHistory[] = []
    const repository = {
      assign: vi.fn().mockResolvedValue(response(occurrence)),
      updateStatus: vi.fn().mockResolvedValue(response(occurrence)),
      finish: vi.fn().mockResolvedValue(response(occurrence)),
      history: vi.fn().mockResolvedValue(response(history)),
    } as unknown as OccurrenceRepository
    const service = createOccurrenceService(repository)

    await service.assign(occurrence.id, 'employee-01', 'employee-user')
    await service.updateStatus(occurrence.id, 'in_progress', 'employee-user')
    await service.finish(occurrence.id, 'employee-user', 'Concluído')

    expect(repository.assign).toHaveBeenCalledWith(occurrence.id, 'employee-01', 'employee-user')
    expect(repository.updateStatus).toHaveBeenCalledWith(occurrence.id, 'in_progress', 'employee-user')
    expect(repository.finish).toHaveBeenCalledWith(occurrence.id, 'employee-user', 'Concluído')
  })
})

describe('autenticação mock por classificação', () => {
  it.each([
    ['resident', 'morador@example.com'],
    ['employee', 'funcionario@example.com'],
    ['syndic', 'sindica@example.com'],
    ['admin', 'admin@example.com'],
  ])('permite login como %s', async (role, email) => {
    const service = createAuthService(mockAuthRepository)
    const result = await service.authenticate({ email, password: 'condovale' })
    expect(result.data.user).toMatchObject({ email, role, status: 'active' })
  })

  it('rejeita senha inválida sem criar sessão', async () => {
    const service = createAuthService(mockAuthRepository)
    await expect(service.authenticate({ email: 'admin@example.com', password: 'errada' }))
      .rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
  })
})

describe('permissões do diagrama de casos de uso', () => {
  const residentCases: UseCase[] = [
    'login', 'update-own-profile', 'view-notices', 'create-occurrence', 'track-own-occurrences',
    'view-common-areas', 'request-reservation', 'view-own-reservations', 'cancel-own-reservation',
  ]
  const employeeCases: UseCase[] = ['login', 'view-assigned-occurrences', 'update-occurrence-progress', 'finish-occurrence']
  const syndicCases: UseCase[] = ['login', 'publish-notices', 'generate-reports']
  const adminCases: UseCase[] = [
    'login', 'manage-units', 'manage-residents', 'link-residents-to-units', 'analyze-occurrences',
    'assign-occurrence', 'publish-notices', 'manage-reservations', 'approve-or-reject-reservation',
  ]

  it.each([
    ['resident', residentCases], ['employee', employeeCases], ['syndic', syndicCases], ['admin', adminCases],
  ] as Array<[UserRole, UseCase[]]>)('%s possui exatamente os casos de uso previstos', (role, allowed) => {
    const allCases = [...rolePermissions[role]]
    expect([...rolePermissions[role]]).toEqual(allowed)
    for (const useCase of allCases) expect(can(role, useCase)).toBe(allowed.includes(useCase))
  })

  it('não concede operações administrativas a moradores ou funcionários', () => {
    const restricted: UseCase[] = ['manage-units', 'manage-residents', 'link-residents-to-units', 'publish-notices', 'approve-or-reject-reservation', 'generate-reports']
    for (const role of ['resident', 'employee'] as UserRole[]) {
      for (const useCase of restricted) expect(can(role, useCase)).toBe(false)
    }
  })

  it.each([
    ['resident', ['Início', 'Ocorrências', 'Reservas', 'Comunicados']],
    ['employee', ['Início', 'Ocorrências']],
    ['syndic', ['Início', 'Comunicados', 'Relatórios']],
    ['admin', ['Início', 'Ocorrências', 'Reservas', 'Comunicados']],
  ] as Array<[UserRole, string[]]>)('%s visualiza somente os módulos permitidos', (role, expected) => {
    const modules = ['Início', 'Ocorrências', 'Reservas', 'Comunicados', 'Relatórios'] as const
    expect(modules.filter(module => canViewModule(role, module))).toEqual(expected)
  })
})
