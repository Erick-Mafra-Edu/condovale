export interface ApiResponse<T> {
  data: T
  message: string | null
  total?: number
  amount?: number
}

export interface PageQuery {
  page?: number
  limit?: number
  filters?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

export function asId(value: string | number | null | undefined): string {
  return value == null ? '' : String(value)
}
