<script setup lang="ts">
import type { AppError } from '~/domain/app-error'
import type { UserRole } from '~/domain/user'

defineProps<{ loading: boolean; error: AppError | null; showDemoAccess: boolean }>()
const emit = defineEmits<{ submit: [email: string, password: string] }>()

const email = ref('morador@example.com')
const password = ref('condovale')
const roleOptions: Array<{ role: UserRole; label: string; email: string }> = [
  { role: 'resident', label: 'Morador', email: 'morador@example.com' },
  { role: 'employee', label: 'Manutenção', email: 'funcionario@example.com' },
  { role: 'employee', label: 'Segurança', email: 'funcionario.seguranca@example.com' },
  { role: 'employee', label: 'Conservação', email: 'funcionario.conservacao@example.com' },
  { role: 'syndic', label: 'Síndico', email: 'sindica@example.com' },
  { role: 'admin', label: 'Administrador', email: 'admin@example.com' },
]

function selectRole(value: string) {
  email.value = value
}

function submit() {
  emit('submit', email.value, password.value)
}
</script>

<template>
  <main class="login-shell">
    <section class="login-art glass" aria-label="Apresentação do CondoVale">
      <div class="login-brand"><span class="brand-mark">⌂</span><span><strong>CondoVale</strong><small>Residencial Vale Verde</small></span></div>
      <div class="login-message"><span class="eyebrow">Comunidade em primeiro lugar</span><h1>Seu condomínio, mais próximo de você.</h1><p>Reserve espaços, acompanhe ocorrências e fique por dentro do que importa.</p></div>
      <div class="login-orbit orbit-one"></div><div class="login-orbit orbit-two"></div><div class="login-building">⌂</div>
      <div class="login-note"><span>✦</span><span><strong>Uma experiência tranquila</strong><small>Informação clara para decisões melhores.</small></span></div>
    </section>

    <section class="login-card glass" aria-labelledby="login-title">
      <div class="mobile-login-brand"><span class="brand-mark">⌂</span><strong>CondoVale</strong></div>
      <span class="eyebrow">Acesso seguro</span><h2 id="login-title">Bem-vindo de volta</h2><p class="login-intro">Entre para acessar o seu condomínio.</p>
      <form @submit.prevent="submit">
        <label for="login-email">E-mail<input id="login-email" v-model="email" type="email" autocomplete="email" required /></label>
        <label for="login-password">Senha<input id="login-password" v-model="password" type="password" autocomplete="current-password" required /></label>
        <p v-if="error" class="login-error" role="alert">{{ error.message }}</p>
        <button class="primary-button login-submit" type="submit" :disabled="loading">{{ loading ? 'Entrando...' : 'Entrar no CondoVale' }}</button>
      </form>
      <div v-if="showDemoAccess" class="demo-access"><span>Testar como</span><div class="role-grid"><button v-for="option in roleOptions" :key="option.email" type="button" :class="['role-chip', { selected: email === option.email }]" @click.prevent="email = option.email">{{ option.label }}</button></div><small>Senha de demonstração: <strong>condovale</strong></small></div>
    </section>
  </main>
</template>

<style scoped>
.login-shell{min-height:100vh;display:grid;grid-template-columns:minmax(320px,1.1fr) minmax(360px,.9fr);gap:clamp(28px,7vw,108px);align-items:center;padding:clamp(24px,6vw,88px);background:#f3fafb;color:#102a43}.login-art,.login-card{border:1px solid rgba(16,42,67,.1);border-radius:32px;box-shadow:0 20px 55px rgba(16,42,67,.12),inset 0 1px 0 rgba(255,255,255,.9)}.login-art{position:relative;min-height:650px;overflow:hidden;padding:42px;background:#E6FFFA;display:flex;flex-direction:column}.login-brand,.mobile-login-brand{display:flex;align-items:center;gap:11px}.login-brand strong{display:block;font-size:18px;letter-spacing:-.02em}.login-brand small{display:block;color:#64748b;font-size:11px;margin-top:2px}.brand-mark{width:38px;height:38px;display:grid;place-items:center;border-radius:13px;background:#008c9e;color:#fff;font-size:22px;box-shadow:0 8px 18px rgba(0,140,158,.22)}.login-message{position:relative;z-index:1;max-width:470px;margin-top:auto;margin-bottom:150px}.eyebrow{display:block;text-transform:uppercase;letter-spacing:.14em;color:#008c9e;font-size:11px;font-weight:800}.login-message h1{max-width:440px;margin:14px 0;font-size:clamp(34px,4.2vw,62px);line-height:1.02;letter-spacing:-.055em}.login-message p,.login-intro{color:#64748b;line-height:1.6}.login-orbit{position:absolute;border:1px solid rgba(0,140,158,.18);border-radius:50%}.orbit-one{width:460px;height:460px;right:-110px;top:105px}.orbit-two{width:330px;height:330px;right:-40px;top:170px;background:rgba(255,255,255,.2)}.login-building{position:absolute;right:115px;top:245px;color:#008c9e;font-size:115px;opacity:.18}.login-note{position:absolute;bottom:42px;left:42px;display:flex;gap:12px;align-items:center;padding:14px 17px;border-radius:18px;background:rgba(255,255,255,.58);border:1px solid rgba(255,255,255,.82);backdrop-filter:blur(12px)}.login-note>span:first-child{color:#008c9e;font-size:20px}.login-note strong,.login-note small{display:block}.login-note small{margin-top:3px;color:#64748b}.login-card{width:min(100%,450px);justify-self:center;padding:clamp(28px,5vw,48px);background:rgba(255,255,255,.88)}.login-card h2{margin:12px 0 6px;font-size:32px;letter-spacing:-.045em}.login-intro{margin:0 0 28px}.login-card label{display:block;margin-top:17px;font-size:13px;font-weight:700}.login-card input{display:block;width:100%;margin-top:8px;padding:13px 14px;border:1px solid #d8e6e8;border-radius:12px;background:#fbfefe;color:#102a43;font:inherit}.login-card input:focus{outline:3px solid rgba(0,140,158,.2);border-color:#008c9e}.login-submit{width:100%;justify-content:center;margin-top:24px}.login-error{padding:10px 12px;border-radius:10px;background:#fff0f1;color:#a62f3b;font-size:13px}.demo-access{border-top:1px solid #e4ecee;margin-top:28px;padding-top:20px;color:#64748b;font-size:12px}.role-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0}.role-chip{padding:9px 8px;border:1px solid #d8e6e8;border-radius:10px;background:#f8fcfc;color:#36566d;font:inherit;cursor:pointer}.role-chip.selected{border-color:#65bcc1;background:#e5f7f8;color:#006a78}.demo-access small{display:block;margin-top:12px}.mobile-login-brand{display:none;margin-bottom:30px}.mobile-login-brand strong{font-size:18px}@media(max-width:900px){.login-shell{grid-template-columns:1fr;padding:18px}.login-art{display:none}.login-card{width:min(100%,500px)}}
@media(prefers-reduced-motion:reduce){.login-card,.login-art{scroll-behavior:auto}}
/* Login inherits the same semantic surfaces as the application shell. */
.login-shell{background:var(--bg);color:var(--navy)}
.login-art{background:var(--surface-raised);border-color:var(--line)}
.login-card{background:var(--surface);color:var(--navy);border-color:var(--line)}
.brand-mark{background:var(--primary);box-shadow:0 8px 18px rgba(0,140,158,.22)}
.eyebrow,.login-note>span:first-child{color:var(--primary)}
.login-message p,.login-intro,.login-brand small,.login-note small,.demo-access{color:var(--neutral)}
.login-card input{background:var(--input);color:var(--navy);border-color:var(--line)}
.login-card input::placeholder{color:var(--neutral)}
.login-card input:focus{outline-color:var(--focus);border-color:var(--secondary)}
.role-chip{background:var(--surface-raised);color:var(--navy);border-color:var(--line)}
.role-chip.selected{border-color:var(--secondary);background:var(--selected);color:var(--deep)}
.login-submit{background:var(--primary)}
.login-error{background:var(--danger-bg);color:var(--danger-text)}
.demo-access{border-color:var(--line)}
.login-note{background:var(--glass);border-color:var(--glass-border)}
.orbit-two{background:color-mix(in srgb,var(--surface-raised) 72%,transparent)}
</style>
