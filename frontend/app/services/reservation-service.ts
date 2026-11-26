import { AppError } from '~/domain/app-error'
import type { CommonAreaInput, CreateReservationInput, ReservationStatus } from '~/domain/reservation'
import type { ReservationRepository } from '~/repositories/contracts/reservation-repository'

export function createReservationService(repository: ReservationRepository) {
  return {
    list: (page?: number, filters?: string) => repository.list(page, filters),
    listAreas: () => repository.listAreas(),
    findById: (id: string) => repository.findById(id),
    listOccupancy: (areaId: string, startDate: string, endDate: string) => repository.listOccupancy(areaId, startDate, endDate),
    availability: (areaId: string, startDate: string, endDate: string) => repository.availability(areaId, startDate, endDate),
    createArea: (input: CommonAreaInput) => repository.createArea(input),
    updateArea: (id: string, input: Partial<CommonAreaInput>) => repository.updateArea(id, input),
    removeArea: (id: string) => repository.removeArea(id),
    getMyReservations: (page?: number, filters?: string) => repository.listMine(page, filters),
    async reserve(input: CreateReservationInput) {
      const fields: Record<string, string[]> = {}
      if (!input.areaId) fields.areaId = ['Selecione uma área']
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
