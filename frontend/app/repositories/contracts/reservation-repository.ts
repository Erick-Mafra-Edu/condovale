import type { ApiResponse } from '~/domain/common'
import type { CommonArea, CreateReservationInput, Reservation, ReservationStatus } from '~/domain/reservation'

export interface ReservationRepository {
  list(): Promise<ApiResponse<Reservation[]>>
  findById(id: string): Promise<ApiResponse<Reservation>>
  create(input: CreateReservationInput): Promise<ApiResponse<Reservation>>
  cancel(id: string): Promise<ApiResponse<Reservation>>
  updateStatus(id: string, status: ReservationStatus, rejectionReason?: string): Promise<ApiResponse<Reservation>>
  listAreas(): Promise<ApiResponse<CommonArea[]>>
  getBlockingReservations(areaId: string, date: string): Promise<ApiResponse<Reservation[]>>
}
