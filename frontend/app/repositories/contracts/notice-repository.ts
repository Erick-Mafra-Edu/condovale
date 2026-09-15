import type { ApiResponse } from '~/domain/common'
import type { Notice, CreateNoticeInput, UpdateNoticeInput } from '~/domain/notice'

export interface NoticeRepository {
  list(): Promise<ApiResponse<Notice[]>>
  findById(id: string): Promise<ApiResponse<Notice>>
  create(input: CreateNoticeInput): Promise<ApiResponse<Notice>>
  update(id: string, input: UpdateNoticeInput): Promise<ApiResponse<Notice>>
  remove(id: string): Promise<ApiResponse<null>>
}

