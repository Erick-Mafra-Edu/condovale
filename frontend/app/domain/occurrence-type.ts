export interface OccurrenceType {
  id: string
  name: string
  description?: string
  employeeCanClose: boolean
  active: boolean
}

export type CreateOccurrenceTypeInput = Pick<OccurrenceType, 'name' | 'description' | 'employeeCanClose' | 'active'>
export type UpdateOccurrenceTypeInput = Partial<CreateOccurrenceTypeInput>
