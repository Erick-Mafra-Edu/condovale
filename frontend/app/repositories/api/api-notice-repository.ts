import type { Notice } from '~/domain/notice'
import type { NoticeRepository } from '~/repositories/contracts/notice-repository'
import { createApiRequest } from './request'
import { asId, type ApiResponse } from '~/domain/common'

interface ApiNotice { id: number; title: string; content: string; author_id: number; author?: { name: string } | null; published_at: string | null; status: Notice['status'] }
function mapNotice(value: ApiNotice): Notice { return { id: asId(value.id), title: value.title, content: value.content, authorId: asId(value.author_id), authorName: value.author?.name, publishedAt: value.published_at, status: value.status } }
function mapOne(response: ApiResponse<ApiNotice>): ApiResponse<Notice> { return { ...response, data: mapNotice(response.data) } }
function mapList(response: ApiResponse<ApiNotice[]>): ApiResponse<Notice[]> { return { ...response, data: response.data.map(mapNotice) } }

export function createApiNoticeRepository(): NoticeRepository {
  const api = createApiRequest()

  return {
    list: async (page = 1, status) => mapList(await api<ApiNotice[]>('/notices', { query: { page, limit: 10, ...(status ? { filters: `status:=:${status}` } : {}) } })),
    findById: async id => mapOne(await api<ApiNotice>(`/notices/${encodeURIComponent(id)}`)),
    create: async input => mapOne(await api<ApiNotice>('/notices', { method: 'POST', body: input })),
    update: async (id, input) => mapOne(await api<ApiNotice>(`/notices/${encodeURIComponent(id)}`, { method: 'PATCH', body: input })),
    remove: id => api<null>(`/notices/${encodeURIComponent(id)}`, { method: 'DELETE' }),
    publish: async id => mapOne(await api<ApiNotice>(`/notices/${encodeURIComponent(id)}/publish`, { method: 'POST' })),
  }
}

