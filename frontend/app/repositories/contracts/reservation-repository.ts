import type { ApiResponse } from '~/domain/common'
import type { AreaAvailability, CommonArea, CommonAreaInput, CreateReservationInput, Reservation, ReservationOccupancy, ReservationStatus } from '~/domain/reservation'

export interface ReservationRepository {
  list(page?: number, filters?: string): Promise<ApiResponse<Reservation[]>>
  listMine(page?: number, filters?: string): Promise<ApiResponse<Reservation[]>>
  findById(id: string): Promise<ApiResponse<Reservation>>
  create(input: CreateReservationInput): Promise<ApiResponse<Reservation>>
  cancel(id: string): Promise<ApiResponse<Reservation>>
  updateStatus(id: string, status: ReservationStatus, rejectionReason?: string): Promise<ApiResponse<Reservation>>
  listAreas(): Promise<ApiResponse<CommonArea[]>>
  listOccupancy(areaId: string, startDate: string, endDate: string): Promise<ApiResponse<ReservationOccupancy[]>>
  availability(areaId: string, startDate: string, endDate: string): Promise<ApiResponse<AreaAvailability>>
  createArea(input: CommonAreaInput): Promise<ApiResponse<CommonArea>>
  updateArea(id: string, input: Partial<CommonAreaInput>): Promise<ApiResponse<CommonArea>>
  removeArea(id: string): Promise<ApiResponse<null>>
}
