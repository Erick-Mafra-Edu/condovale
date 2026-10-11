import type { User } from './user'
import { z } from 'zod'

/** Credenciais transitórias: nunca são anexadas ao objeto User nem persistidas pelo frontend. */
export const loginInputSchema = z.object({
  email: z.string().trim().email('Informe um e-mail válido'),
  password: z.string().min(1, 'Informe a senha'),
})

export type LoginInput = z.infer<typeof loginInputSchema>

export const changePasswordInputSchema = z.object({
  current_password: z.string().min(1, 'Informe a sua senha atual.'),
  password: z.string().min(8, 'A nova senha deve ter pelo menos 8 caracteres.'),
  password_confirmation: z.string().min(1, 'Confirme a nova senha.'),
}).refine(input => input.password === input.password_confirmation, {
  path: ['password_confirmation'],
  message: 'A confirmação da nova senha não confere.',
})

export type ChangePasswordInput = z.infer<typeof changePasswordInputSchema>

/** Resposta segura de sessão exposta à interface após o backend autenticar o usuário. */
export interface AuthSession {
  user: User
}
