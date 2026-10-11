<script setup lang="ts">
import type { AppError } from '~/domain/app-error'
import type { ChangePasswordInput } from '~/domain/auth'

const props = defineProps<{
  loading: boolean
  error: AppError | null
  onSubmit: (input: ChangePasswordInput) => Promise<unknown>
  onLogout: () => Promise<void>
}>()

const form = reactive<ChangePasswordInput>({ current_password: '', password: '', password_confirmation: '' })
const submitting = ref(false)

async function submit() {
  submitting.value = true
  try { await props.onSubmit({ ...form }) } catch { /* o erro já está exposto pelo composable */ }
  finally { submitting.value = false }
}
</script>

<template>
  <main class="password-change-shell">
    <section class="password-change-card glass" aria-labelledby="password-change-title">
      <span class="eyebrow">Primeiro acesso</span>
      <h1 id="password-change-title">Crie uma nova senha</h1>
      <p>Por segurança, troque a senha temporária antes de continuar.</p>
      <form @submit.prevent="submit">
        <label>Senha atual<input v-model="form.current_password" type="password" autocomplete="current-password" required /></label>
        <label>Nova senha<input v-model="form.password" type="password" autocomplete="new-password" minlength="8" required /></label>
        <label>Confirmar nova senha<input v-model="form.password_confirmation" type="password" autocomplete="new-password" minlength="8" required /></label>
        <p v-if="error" class="form-error" role="alert">{{ error.message }}</p>
        <button class="primary-button" type="submit" :disabled="loading || submitting">{{ loading || submitting ? 'Salvando...' : 'Salvar nova senha' }}</button>
      </form>
      <button class="outline-button" type="button" @click="onLogout">Sair</button>
    </section>
  </main>
</template>

<style scoped>
.password-change-shell{min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg)}
.password-change-card{width:min(100%,440px);padding:clamp(28px,5vw,48px);border-radius:24px}
.password-change-card h1{margin:12px 0 8px;font-size:30px;letter-spacing:-.04em}.password-change-card p{color:var(--neutral);line-height:1.5}
.password-change-card label{display:block;margin-top:18px;font-size:12px;font-weight:700}.password-change-card input{display:block;width:100%;margin-top:7px;padding:12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--navy);font:inherit}
.password-change-card .primary-button{width:100%;justify-content:center;margin-top:24px}.password-change-card .outline-button{width:100%;justify-content:center;margin-top:12px}.form-error{color:#b4232c!important;font-size:12px}
</style>
