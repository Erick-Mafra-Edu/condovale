import type { User } from './user'
import { z } from 'zod'

/** Credenciais transitórias: nunca são anexadas ao objeto User nem persistidas pelo frontend. */
export const loginInputSchema = z.object({
  email: z.string().trim().email('Informe um e-mail válido'),
  password: z.string().min(1, 'Informe a senha'),
})

export type LoginInput = z.infer<typeof loginInputSchema>

/** Resposta segura de sessão exposta à interface após o backend autenticar o usuário. */
export interface AuthSession {
  user: User
}
