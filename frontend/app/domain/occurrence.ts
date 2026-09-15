export type OccurrenceStatus = 'open' | 'analysis' | 'assigned' | 'in_progress' | 'completed' | 'cancelled'
export type OccurrenceHistoryType = 'created' | 'status_changed' | 'assigned' | 'comment' | 'completed'

export interface Occurrence {
  id: string
  title: string
  description: string
  category: string
  residentId: string
  unitId?: string
  assignedEmployeeId?: string
  status: OccurrenceStatus
  createdAt: string
  updatedAt: string
  completedAt?: string
}

export interface OccurrenceHistory {
  id: string
  occurrenceId: string
  type: OccurrenceHistoryType
  userId: string
  message?: string
  previousStatus?: OccurrenceStatus
  newStatus?: OccurrenceStatus
  createdAt: string
}

export type CreateOccurrenceInput = Pick<Occurrence, 'title' | 'description' | 'category' | 'residentId' | 'unitId'>
