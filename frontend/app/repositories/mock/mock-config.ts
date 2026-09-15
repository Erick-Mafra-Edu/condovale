import { reactive } from 'vue'
import { AppError } from '~/domain/app-error'

export const mockConfig = reactive({
  latency: 350,
  shouldFail: false,
})

export async function simulateRequest(): Promise<void> {
  await new Promise(resolve => setTimeout(resolve, mockConfig.latency))
  if (mockConfig.shouldFail) {
    throw new AppError('SERVER_ERROR', 'Erro simulado do servidor')
  }
}
