export type OccurrenceStatus = 'open' | 'analysis' | 'assigned' | 'in_progress' | 'completed' | 'cancelled'
export type OccurrenceHistoryType = 'created' | 'status_changed' | 'assigned' | 'comment' | 'completed'

export interface Occurrence {
  id: string
  title: string
  description: string
  category: string
  occurrenceTypeId?: string
  occurrenceTypeName?: string
  employeeCanClose?: boolean
  residentId: string
  residentName?: string
  unitId?: string
  unitCode?: string
  unitNumber?: string
  assignedEmployeeId?: string
  assignedEmployeeName?: string
  resolved?: boolean
  conclusion?: string
  status: OccurrenceStatus
  createdAt: string
  updatedAt: string
  completedAt?: string
  attachments?: OccurrenceAttachment[]
}

export interface OccurrenceAttachment {
  id: string
  occurrenceId: string
  uploadedBy: string
  originalName: string
  mimeType: string
  size: number
  width?: number
  height?: number
  url: string
}

export interface OccurrenceEmployee {
  id: string
  name: string
}

export interface OccurrenceHistory {
  id: string
  occurrenceId: string
  type: OccurrenceHistoryType
  userId: string
  userName?: string
  message?: string
  previousStatus?: OccurrenceStatus
  newStatus?: OccurrenceStatus
  createdAt: string
}

export type CreateOccurrenceInput = {
  title: string
  description: string
  occurrenceTypeId: string | number
  residentId?: string | number
}

export type FinishOccurrenceInput = {
  resolved: boolean
  conclusion: string
}

export type OccurrenceAttendanceInput = {
  status?: OccurrenceStatus
  message?: string
}
