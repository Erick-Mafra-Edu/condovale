export type CommonAreaStatus = 'available' | 'unavailable'
export type ReservationStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

export interface CommonArea {
  id: string
  name: string
  description?: string
  imageUrl?: string
  capacity?: number
  openingTime?: string
  closingTime?: string
  requiresApproval: boolean
  status: CommonAreaStatus
}

export interface Reservation {
  id: string
  areaId: string
  residentId: string
  date: string
  startTime: string
  endTime: string
  status: ReservationStatus
  createdAt: string
  rejectionReason?: string
}

export type CreateReservationInput = Pick<Reservation, 'areaId' | 'residentId' | 'date' | 'startTime' | 'endTime'>
