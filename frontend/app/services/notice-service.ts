import { AppError } from '~/domain/app-error'
import type { CreateNoticeInput, UpdateNoticeInput } from '~/domain/notice'
import type { NoticeRepository } from '~/repositories/contracts/notice-repository'

function validate(input: UpdateNoticeInput) {
  const fields: Record<string, string[]> = {}
  if (input.title !== undefined && !input.title.trim()) fields.title = ['Informe o título']
  if (input.content !== undefined && !input.content.trim()) fields.content = ['Informe o conteúdo']
  if (Object.keys(fields).length) throw new AppError('VALIDATION_ERROR', 'Revise os campos informados', fields)
}

export function createNoticeService(repository: NoticeRepository) {
  return {
    list: () => repository.list(),
    findById: (id: string) => repository.findById(id),
    async create(input: CreateNoticeInput) {
      validate(input)
      return repository.create(input)
    },
    async update(id: string, input: UpdateNoticeInput) {
      validate(input)
      return repository.update(id, input)
    },
    remove: (id: string) => repository.remove(id),
  }
}

