export interface Notice {
  id: string
  title: string
  content: string
  authorId: string
  publishedAt: string
  status: 'draft' | 'published'
}

export type CreateNoticeInput = Omit<Notice, 'id'>
export type UpdateNoticeInput = Partial<CreateNoticeInput>
