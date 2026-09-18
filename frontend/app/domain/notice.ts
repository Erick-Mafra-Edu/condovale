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

export function getPublishedNotices(notices: readonly Notice[]) {
  return notices
    .filter(notice => notice.status === 'published')
    .sort((a, b) => b.publishedAt.localeCompare(a.publishedAt))
}
