export interface OccurrenceType {
  id: string
  name: string
  description?: string
  kind: 'maintenance' | 'complaint'
  employeeCanClose: boolean
  active: boolean
}

export type CreateOccurrenceTypeInput = Pick<OccurrenceType, 'name' | 'description' | 'kind' | 'employeeCanClose' | 'active'>
export type UpdateOccurrenceTypeInput = Partial<CreateOccurrenceTypeInput>
