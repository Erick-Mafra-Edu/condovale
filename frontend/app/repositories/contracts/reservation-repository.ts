import type { ApiResponse } from '~/domain/common'
import type { CommonArea, CreateReservationInput, Reservation, ReservationOccupancy, ReservationStatus } from '~/domain/reservation'

export interface ReservationRepository {
  list(): Promise<ApiResponse<Reservation[]>>
  listMine(): Promise<ApiResponse<Reservation[]>>
  findById(id: string): Promise<ApiResponse<Reservation>>
  create(input: CreateReservationInput): Promise<ApiResponse<Reservation>>
  cancel(id: string): Promise<ApiResponse<Reservation>>
  updateStatus(id: string, status: ReservationStatus, userId: string, rejectionReason?: string): Promise<ApiResponse<Reservation>>
  listAreas(): Promise<ApiResponse<CommonArea[]>>
  listOccupancy(areaId: string, startDate: string, endDate: string): Promise<ApiResponse<ReservationOccupancy[]>>
}
