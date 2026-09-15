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

export function useServices() {
  const { public: config } = useRuntimeConfig()
  const useMock = config.dataSource !== 'api'

  return {
    reservationService: createReservationService(useMock ? mockReservationRepository : createApiReservationRepository()),
    occurrenceService: createOccurrenceService(useMock ? mockOccurrenceRepository : createApiOccurrenceRepository()),
    noticeService: createNoticeService(useMock ? mockNoticeRepository : createApiNoticeRepository()),
    userService: createUserService(useMock ? mockUserRepository : createApiUserRepository()),
    unitService: createUnitService(useMock ? mockUnitRepository : createApiUnitRepository()),
  }
}
