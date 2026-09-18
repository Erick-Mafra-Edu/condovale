import { AppError } from '~/domain/app-error'
import type { ApiResponse } from '~/domain/common'
import type { Notice } from '~/domain/notice'
import type { NoticeRepository } from '~/repositories/contracts/notice-repository'
import { simulateRequest } from './mock-config'

const notices: Notice[] = [
  { id: 'notice-01', title: 'Bem-vindos ao CondoVale', content: 'Acompanhe os comunicados do condomínio.', authorId: 'user-admin', publishedAt: '2026-09-10T10:00:00Z', status: 'published' },
  { id: 'notice-02', title: 'Manutenção preventiva dos elevadores', content: 'Os elevadores passarão por manutenção preventiva no dia 18 de setembro.', authorId: 'user-admin', publishedAt: '2026-09-15T14:00:00Z', status: 'published' },
  { id: 'notice-03', title: 'Assembleia geral do condomínio', content: 'A assembleia será realizada no salão de festas no dia 25 de setembro.', authorId: 'user-syndic', publishedAt: '2026-09-13T18:00:00Z', status: 'published' },
  { id: 'notice-04', title: 'Atualização das regras de acesso', content: 'Consulte as orientações atualizadas para visitantes e prestadores de serviço.', authorId: 'user-syndic', publishedAt: '2026-09-12T09:00:00Z', status: 'published' },
  { id: 'notice-05', title: 'Comunicado em preparação', content: 'Este rascunho não deve ser exibido aos moradores.', authorId: 'user-admin', publishedAt: '2026-09-17T10:00:00Z', status: 'draft' },
]

function response<T>(data: T): ApiResponse<T> {
  return { data: structuredClone(data), message: null }
}

function ensureNotice(id: string): Notice {
  const item = notices.find(item => item.id === id)
  if (!item) throw new AppError('NOT_FOUND', 'Comunicado não encontrado')
  return item
}

export const mockNoticeRepository: NoticeRepository = {
  async list() {
    await simulateRequest()
    return response(notices)
  },
  async findById(id) {
    await simulateRequest()
    return response(ensureNotice(id))
  },
  async create(input) {
    await simulateRequest()
    const item: Notice = { ...structuredClone(input), id: crypto.randomUUID() }
    notices.push(item)
    return response(item)
  },
  async update(id, input) {
    await simulateRequest()
    const item = ensureNotice(id)
    Object.assign(item, structuredClone(input))
    return response(item)
  },
  async remove(id) {
    await simulateRequest()
    const item = ensureNotice(id)
    notices.splice(notices.indexOf(item), 1)
    return response(null)
  },
}
