export interface Notice {
  id: string
  title: string
  content: string
  authorId: string
  authorName?: string
  publishedAt: string | null
  status: 'draft' | 'published'
}

export type CreateNoticeInput = Pick<Notice, 'title' | 'content' | 'status'>
export type UpdateNoticeInput = Partial<Pick<Notice, 'title' | 'content'>>

export function getPublishedNotices(notices: readonly Notice[]) {
  return notices
    .filter(notice => notice.status === 'published')
    .sort((a, b) => (b.publishedAt ?? '').localeCompare(a.publishedAt ?? ''))
}
