export interface AuditLog {
  id: string
  userId: string
  action: string
  entity: 'reservation' | 'occurrence' | 'notice' | 'user' | 'unit'
  entityId: string
  createdAt: string
  metadata?: Record<string, unknown>
}
