export interface DashboardCard {
  key: string
  label: string
  value: number
  note: string
}

export interface DashboardEntry {
  id: number
  title?: string
  status?: string
  date?: string
  published_at?: string | null
  common_area?: { id: number; name: string } | null
}

export interface DashboardData {
  generated_at: string
  cards: DashboardCard[]
  my_occurrences?: { open: number; latest: DashboardEntry[] }
  my_reservations?: { upcoming_total: number; upcoming: DashboardEntry[] }
  my_assignments?: { to_start: number; in_progress: number; completed_this_month: number; queue: DashboardEntry[] }
  occurrence_queue?: { open_total: number; awaiting_analysis: number; oldest_waiting: DashboardEntry[] }
  reservations?: { awaiting_approval: number; upcoming: DashboardEntry[] }
  units?: { active: number; occupied: number }
  people?: { residents_active: number }
  month?: { from: string; to: string }
  notices?: { published: number; drafts?: number; latest: DashboardEntry[] }
  recent_activity?: Array<{ id: number; description: string; created_at: string }>
}
