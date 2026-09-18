import { createApiReservationRepository } from '~/repositories/api/api-reservation-repository'
import { mockReservationRepository } from '~/repositories/mock/mock-reservation-repository'
import { createReservationService } from './reservation-service'
import { createApiOccurrenceRepository } from '~/repositories/api/api-occurrence-repository'
import { mockOccurrenceRepository } from '~/repositories/mock/mock-occurrence-repository'
import { createOccurrenceService } from './occurrence-service'
import { createApiNoticeRepository } from '~/repositories/api/api-notice-repository'
import { mockNoticeRepository } from '~/repositories/mock/mock-notice-repository'
import { createNoticeService } from './notice-service'
import { createApiUserRepository } from '~/repositories/api/api-user-repository'
import { mockUserRepository } from '~/repositories/mock/mock-user-repository'
import { createUserService } from './user-service'
import { createApiUnitRepository } from '~/repositories/api/api-unit-repository'
import { mockUnitRepository } from '~/repositories/mock/mock-unit-repository'
import { createUnitService } from './unit-service'
import { createApiAuthRepository } from '~/repositories/api/api-auth-repository'
import { mockAuthRepository } from '~/repositories/mock/mock-auth-repository'
import { createAuthService } from './auth-service'
import { createApiAuditRepository } from '~/repositories/api/api-audit-repository'
import { mockAuditRepository } from '~/repositories/mock/mock-audit-repository'
import { createAuditService } from './audit-service'

export function useServices() {
  const { public: config } = useRuntimeConfig()
  const useMock = config.dataSource !== 'api'

  return {
    authService: createAuthService(useMock ? mockAuthRepository : createApiAuthRepository()),
    auditService: createAuditService(useMock ? mockAuditRepository : createApiAuditRepository()),
    reservationService: createReservationService(useMock ? mockReservationRepository : createApiReservationRepository()),
    occurrenceService: createOccurrenceService(useMock ? mockOccurrenceRepository : createApiOccurrenceRepository()),
    noticeService: createNoticeService(useMock ? mockNoticeRepository : createApiNoticeRepository()),
    userService: createUserService(useMock ? mockUserRepository : createApiUserRepository()),
    unitService: createUnitService(useMock ? mockUnitRepository : createApiUnitRepository()),
  }
}
