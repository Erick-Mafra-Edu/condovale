import type { Occurrence, OccurrenceStatus } from './occurrence'

export interface ReportFilters {
  from: string
  to: string
  category?: string
  status?: OccurrenceStatus | 'all'
}

export interface ReportPagination {
  page: number
  perPage: number
}

/** Contrato da consulta; exportAll ignora paginação para gerar o documento completo. */
export interface ReportQuery {
  filters: ReportFilters
  pagination: ReportPagination
  exportAll?: boolean
}

export interface ReportResult {
  data: Occurrence[]
  pagination: ReportPagination & { total: number; totalPages: number }
}
