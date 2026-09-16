import type { Occurrence } from '~/domain/occurrence'
import type { CommonArea, Reservation } from '~/domain/reservation'

export const mockAreas: CommonArea[] = [
  { id: 'area-01', name: 'Salão de festas', description: 'Espaço para eventos', capacity: 50, openingTime: '08:00', closingTime: '23:00', requiresApproval: true, status: 'available' },
  { id: 'area-02', name: 'Churrasqueira', capacity: 20, openingTime: '09:00', closingTime: '22:00', requiresApproval: false, status: 'available' },
  { id: 'area-03', name: 'Quadra', description: 'Quadra poliesportiva', capacity: 12, openingTime: '08:00', closingTime: '22:00', requiresApproval: false, status: 'available' },
]

export const mockReservations: Reservation[] = [
  { id: 'reservation-03', areaId: 'area-01', residentId: 'user-02', date: '2026-09-04', startTime: '10:00', endTime: '14:00', status: 'approved', createdAt: '2026-09-01T10:00:00Z' },
  { id: 'reservation-04', areaId: 'area-01', residentId: 'user-02', date: '2026-09-11', startTime: '08:00', endTime: '23:00', status: 'approved', createdAt: '2026-09-02T10:00:00Z' },
  { id: 'reservation-01', areaId: 'area-01', residentId: 'user-01', date: '2026-09-20', startTime: '19:00', endTime: '23:00', status: 'approved', createdAt: '2026-09-10T10:00:00Z' },
  { id: 'reservation-02', areaId: 'area-02', residentId: 'user-01', date: '2026-09-23', startTime: '12:00', endTime: '16:00', status: 'pending', createdAt: '2026-09-14T12:00:00Z' },
  { id: 'reservation-05', areaId: 'area-03', residentId: 'user-02', date: '2026-09-08', startTime: '08:00', endTime: '12:00', status: 'approved', createdAt: '2026-09-03T10:00:00Z' },
]

export const mockOccurrences: Occurrence[] = [
  { id: 'occurrence-01', title: 'Vazamento na garagem B', description: 'Há água escorrendo próximo às vagas do subsolo.', category: 'Manutenção', residentId: 'user-01', unitId: 'unit-01', status: 'open', createdAt: '2026-09-14T10:00:00Z', updatedAt: '2026-09-14T10:00:00Z' },
  { id: 'occurrence-02', title: 'Portão social não fecha', description: 'O portão de acesso social permanece aberto após a passagem.', category: 'Segurança', residentId: 'user-01', unitId: 'unit-01', status: 'analysis', createdAt: '2026-09-13T15:30:00Z', updatedAt: '2026-09-14T08:00:00Z' },
]
