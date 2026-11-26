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
  maxReservationMinutes?: number
  requiresApproval: boolean
  status: CommonAreaStatus
}

export interface Reservation {
  id: string
  areaId: string
  residentId: string
  date: string
  /** Ausentes em reservas de diária (dia inteiro). */
  startTime?: string
  endTime?: string
  status: ReservationStatus
  createdAt: string
  rejectionReason?: string
  residentName?: string
  unitCode?: string
}

export type CreateReservationInput = Pick<Reservation, 'areaId' | 'date' | 'startTime' | 'endTime'>
export type ReservationOccupancy = Pick<Reservation, 'date' | 'startTime' | 'endTime'>

export interface AvailabilityPeriod { startTime: string | null; endTime: string | null; minutes?: number }
export interface AvailabilitySlot { startTime: string; endTime: string; status: 'available' | 'occupied' | 'past' }
export interface AvailabilityDay {
  date: string
  status: 'available' | 'partial' | 'occupied' | 'unavailable'
  reason: string | null
  reasonLabel: string | null
  fullDayAvailable: boolean
  freePeriods: AvailabilityPeriod[]
  occupiedPeriods: AvailabilityPeriod[]
  slots: AvailabilitySlot[]
}
export interface AreaAvailability { area: CommonArea; startDate: string; endDate: string; slotMinutes: number; days: AvailabilityDay[] }

export type CommonAreaInput = Omit<CommonArea, 'id'>
