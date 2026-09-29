import type { Unit, User } from './user'

export type OccupantType = 'owner' | 'tenant' | 'dependent'

export interface UnitOccupancy {
  id: string
  unit_id: string
  user_id: string
  occupant_type: OccupantType
  started_at: string
  ended_at: string | null
  is_active: boolean
  user?: User
  unit?: Unit
}

export type CreateUnitOccupancyInput = Pick<UnitOccupancy, 'user_id' | 'unit_id'> &
  Partial<Pick<UnitOccupancy, 'occupant_type' | 'started_at'>>
