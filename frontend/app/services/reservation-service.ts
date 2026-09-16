import { AppError } from '~/domain/app-error'
import type { CreateReservationInput, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'

export function createReservationService(repository: ReservationRepository) {
  return {
    list: () => repository.list(),
    listAreas: () => repository.listAreas(),
    findById: (id: string) => repository.findById(id),
    getBlockingReservations: (areaId: string, date: string) => repository.getBlockingReservations(areaId, date),
    getMyReservations: async (residentId: string) => {
      const result = await repository.list()
      return { ...result, data: result.data.filter(item => item.residentId === residentId) }
    },
    async reserve(input: CreateReservationInput) {
      const fields: Record<string, string[]> = {}
      if (!input.areaId) fields.areaId = ['Selecione uma área']
      if (!input.residentId) fields.residentId = ['Informe o morador']
      if (!input.date) fields.date = ['Informe uma data']
      const hasOnlyOneTime = Boolean(input.startTime) !== Boolean(input.endTime)
      if (hasOnlyOneTime || (input.startTime && input.endTime && input.startTime >= input.endTime)) fields.startTime = ['Informe os dois horários ou deixe ambos vazios para reservar o dia inteiro']
      if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise os dados da reserva', fields)
      return repository.create(input)
    },
    cancel: (id: string) => repository.cancel(id),
    updateStatus: (id: string, status: ReservationStatus, reason?: string) => repository.updateStatus(id, status, reason),
  }
}
