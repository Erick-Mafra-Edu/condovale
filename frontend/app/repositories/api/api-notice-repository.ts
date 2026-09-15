import type { Notice } from '~/domain/notice'
import type { NoticeRepository } from '~/repositories/contracts/notice-repository'
import { createApiRequest } from './request'

export function createApiNoticeRepository(): NoticeRepository {
  const api = createApiRequest()

  return {
    list: () => api<Notice[]>('/notices'),
    findById: id => api<Notice>(`/notices/${encodeURIComponent(id)}`),
    create: input => api<Notice>('/notices', { method: 'POST', body: input }),
    update: (id, input) => api<Notice>(`/notices/${encodeURIComponent(id)}`, { method: 'PATCH', body: input }),
    remove: id => api<null>(`/notices/${encodeURIComponent(id)}`, { method: 'DELETE' }),
  }
}

